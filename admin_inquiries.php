<?php
// admin_inquiries.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Allow Super Admin (1) and NGO Admin (2)
Middleware::role([1, 2]);

$pdo = getDatabase();
$role_id = $_SESSION['role_id'];

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

// Handle POST actions (e.g. mark as read, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        
        if ($id) {
            try {
                if ($action === 'mark_read') {
                    $stmt = $pdo->prepare("UPDATE contact_inquiries SET status = 'read' WHERE inquiry_id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Inquiry marked as read.";
                } elseif ($action === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE inquiry_id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Inquiry deleted.";
                }
            } catch (PDOException $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR message LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($status_filter) {
    if ($status_filter === 'unread') {
        $whereClauses[] = "status = 'pending'"; // pending implies unread basically in this design
    } else {
        $whereClauses[] = "status = :status";
        $params[':status'] = $status_filter;
    }
}
if ($date_filter) {
    if ($date_filter === 'today') {
        $whereClauses[] = "DATE(submitted_at) = CURDATE()";
    } elseif ($date_filter === 'week') {
        $whereClauses[] = "YEARWEEK(submitted_at, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($date_filter === 'month') {
        $whereClauses[] = "MONTH(submitted_at) = MONTH(CURDATE()) AND YEAR(submitted_at) = YEAR(CURDATE())";
    }
}

$whereSQL = implode(" AND ", $whereClauses);

try {
    // KPIs
    $kpiStmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN DATE(submitted_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM contact_inquiries");
    $stats = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    // Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_inquiries WHERE $whereSQL");
    $countStmt->execute($params);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch Records
    $query = "SELECT * FROM contact_inquiries WHERE $whereSQL ORDER BY submitted_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}


// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'inquiry_reply') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $stmt = $pdo->prepare("SELECT * FROM contact_inquiries WHERE inquiry_id = ?");
    $stmt->execute([$id]);
    $inq = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($inq) {
        // Mark as read when opened
        if ($inq['status'] === 'new') {
            $update = $pdo->prepare("UPDATE contact_inquiries SET status = 'read' WHERE inquiry_id = ?");
            $update->execute([$id]);
            $inq['status'] = 'read';
        }
        ?>
        <div class="modal">
            <div class="modal-header">
                <h2>Inquiry Details</h2>
                <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div style="background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <div>
                            <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($inq['name'] ?? ''); ?></strong>
                            <div style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($inq['email'] ?? ''); ?></div>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge" style="background: rgba(0,0,0,0.05); color: var(--text-dark);"><?php echo ucfirst(htmlspecialchars($inq['status'] ?? '')); ?></span>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;"><?php echo date('M d, Y h:i A', strtotime($inq['submitted_at'])); ?></div>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <strong style="display: block; margin-bottom: 5px; color: var(--text-dark);">Subject: <?php echo htmlspecialchars($inq['subject'] ?? ''); ?></strong>
                        <p style="color: var(--text-muted); line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($inq['message'] ?? ''); ?></p>
                    </div>
                </div>

                <form method="POST" action="<?php echo basename(__FILE__); ?>" class="ajax-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="action" value="reply_inquiry">
                    <input type="hidden" name="inquiry_id" value="<?php echo $inq['inquiry_id'] ?? ''; ?>">
                    
                    <div class="form-group">
                        <label>Send Reply</label>
                        <textarea class="form-control" name="reply_message" rows="4" required placeholder="Type your response here..."></textarea>
                    </div>
                    
                    <div class="modal-footer" style="padding-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted); font-size: 0.9rem;">
                            <input type="checkbox" name="mark_resolved" value="1" style="width: 16px; height: 16px;"> Mark as resolved
                        </label>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn-secondary" data-modal-close="true">Close</button>
                            <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    exit;
}
// --- END AJAX MODAL HANDLER ---

?>
<?php 

// --- AJAX MODAL POST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] ?? '') === 'update_inquiry') {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $internal_notes = htmlspecialchars(trim($_POST['internal_notes']));
        
        try {
            $stmt = $pdo->prepare("UPDATE contact_inquiries SET status = ?, priority = ?, internal_notes = ? WHERE inquiry_id = ?");
            $stmt->execute([$status, $priority, $internal_notes, $id]);
            // If ajax, this will just reload the page/table via the JS handler
        } catch (PDOException $e) {
            // handle error
        }
    }
}
// -------------------------------


$page_title = "Contact Inquiries";

require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Contact Inquiries</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Contact Inquiries</span>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="kpi-grid" style="margin-bottom: 2rem;">
                <?php 
                render_kpi_card('Total Inquiries', $stats['total'], 'fas fa-envelope', 'trend-neutral', 'Total Received');
                render_kpi_card('Unread / Pending', $stats['unread'], 'fas fa-envelope-open', 'trend-down', 'Action Required');
                render_kpi_card('Resolved', $stats['resolved'], 'fas fa-check-circle', 'trend-up', 'Completed');
                render_kpi_card('Today', $stats['today'], 'far fa-clock', 'trend-neutral', 'New Today');
                ?>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert-message alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-message alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- Filter & Table Card -->
            <div class="glass-card">
                <div class="card-header" style="flex-wrap: wrap; gap: 15px;">
                    <h3 class="card-title">All Inquiries</h3>
                    
                    <form method="GET" class="filter-form" style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%; justify-content: flex-end;">
                        <input type="text" name="search" class="form-control" placeholder="Search name, email..." value="<?php echo htmlspecialchars($search); ?>" style="width: auto; flex-grow: 1; max-width: 250px;">
                        
                        <select name="status" class="form-control" style="width: auto;">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                        
                        <select name="date_filter" class="form-control" style="width: auto;">
                            <option value="">Any Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                        
<?php $prefix = ($role_id == 2) ? 'ngo' : 'admin'; ?>
                        <button type="submit" class="btn-primary" style="padding: 0 15px;"><i class="fas fa-search"></i></button>
                        <?php if ($search || $status_filter || $date_filter): ?>
                            <a href="<?php echo $prefix; ?>_inquiries.php" class="btn-primary" style="background: var(--surface-hover); color: var(--text-dark); border: 1px solid rgba(0,0,0,0.1); padding: 0 15px; display: flex; align-items: center; text-decoration: none;"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiries)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem;">No inquiries found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inquiries as $inquiry): 
                                    $subject = substr($inquiry['message'], 0, 50) . (strlen($inquiry['message']) > 50 ? '...' : '');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($inquiry['email'] ?? ''); ?></td>
                                    <td><span class="inquiry-subject"><?php echo htmlspecialchars($subject); ?></span></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($inquiry['submitted_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($inquiry['status'] ?? '') == 'resolved' ? 'active' : ($inquiry['status'] == 'read' ? 'info' : 'pending'); ?>">
                                            <?php echo ucfirst($inquiry['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background: <?php echo ($inquiry['priority'] ?? '') == 'high' ? 'rgba(220,38,38,0.1)' : ($inquiry['priority'] == 'medium' ? 'rgba(245,158,11,0.1)' : 'rgba(59,130,246,0.1)'); ?>; color: <?php echo ($inquiry['priority'] ?? '') == 'high' ? '#DC2626' : ($inquiry['priority'] == 'medium' ? '#F59E0B' : '#3B82F6'); ?>;">
                                            <?php echo ucfirst($inquiry['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button data-ajax-modal="true" data-url="<?php echo basename(__FILE__); ?>?modal=inquiry_details&id=<?php echo $inquiry['inquiry_id'] ?? ''; ?>" class="action-btn view-btn" title="View Detail"><i class="fas fa-eye"></i></button>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this inquiry?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $inquiry['inquiry_id'] ?? ''; ?>">
                                            <button type="submit" class="action-btn delete-btn" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(0,0,0,0.05);">
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
                        </div>
                        <div class="pagination" style="display: flex; gap: 5px;">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>" 
                                   class="btn-primary" style="padding: 5px 12px; font-size: 0.85rem; <?php echo $page == $i ? '' : 'background: rgba(0,0,0,0.05); color: var(--text-dark); border: none;'; ?>">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

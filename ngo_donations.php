<?php
// admin_donations.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only NGO Admin (Role ID 2) can access
Middleware::role([2]);

$pdo = getDatabase();

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(d.transaction_id LIKE :search OR d.receipt_number LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($status_filter) {
    $whereClauses[] = "d.payment_status = :status";
    $params[':status'] = $status_filter;
}
if ($start_date) {
    $whereClauses[] = "DATE(d.donation_date) >= :start_date";
    $params[':start_date'] = $start_date;
}
if ($end_date) {
    $whereClauses[] = "DATE(d.donation_date) <= :end_date";
    $params[':end_date'] = $end_date;
}

$whereSQL = implode(" AND ", $whereClauses);

// CSV Export Logic
if (isset($_GET['export']) && ($_GET['export'] ?? '') === 'csv') {
    $query = "SELECT d.id, d.transaction_id, d.receipt_number, d.amount, d.currency, d.payment_status, d.donation_date, 
                     u.full_name as donor_name, u.email as donor_email, c.name as campaign_name 
              FROM donations d 
              LEFT JOIN users u ON d.donor_id = u.id 
              LEFT JOIN campaigns c ON d.campaign_id = c.id 
              WHERE $whereSQL 
              ORDER BY d.donation_date DESC";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=donations_export_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Transaction ID', 'Receipt Number', 'Amount', 'Currency', 'Status', 'Date', 'Donor Name', 'Donor Email', 'Campaign Name']);
    
    foreach ($exportData as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

try {
    // Financial Summary
    $summaryStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN d.payment_status = 'completed' THEN d.amount ELSE 0 END) as total_collected,
            SUM(CASE WHEN d.payment_status = 'pending' THEN d.amount ELSE 0 END) as total_pending,
            SUM(CASE WHEN d.payment_status = 'refunded' THEN d.amount ELSE 0 END) as total_refunded
        FROM donations d 
        LEFT JOIN users u ON d.donor_id = u.id 
        WHERE $whereSQL
    ");
    foreach ($params as $key => $val) {
        $summaryStmt->bindValue($key, $val);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // Total Count for Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM donations d LEFT JOIN users u ON d.donor_id = u.id WHERE $whereSQL");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalDonations = $countStmt->fetchColumn();
    $totalPages = ceil($totalDonations / $limit);

    // Fetch Donations
    $query = "SELECT d.*, u.full_name as donor_name, u.email as donor_email, c.name as campaign_name, r.pdf_path 
              FROM donations d 
              LEFT JOIN users u ON d.donor_id = u.id 
              LEFT JOIN campaigns c ON d.campaign_id = c.id 
              LEFT JOIN donation_receipts r ON d.id = r.donation_id
              WHERE $whereSQL 
              ORDER BY d.donation_date DESC 
              LIMIT :limit OFFSET :offset";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $donations = [];
    $summary = ['total_collected' => 0, 'total_pending' => 0, 'total_refunded' => 0];
    $totalPages = 1;
}

?>
<?php 

// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'donation_details') {
    $don_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $stmt = $pdo->prepare("
        SELECT d.*, c.name as campaign_name, u.full_name as donor_name, u.email as donor_email 
        FROM donations d 
        LEFT JOIN campaigns c ON d.campaign_id = c.id 
        LEFT JOIN users u ON d.donor_id = u.id 
        WHERE d.id = ?
    ");
    $stmt->execute([$don_id]);
    $don = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($don) {
        $donorName = $don['is_anonymous'] ? 'Anonymous' : htmlspecialchars($don['donor_name'] ?? '');
        $donorEmail = $don['is_anonymous'] ? 'N/A' : htmlspecialchars($don['donor_email'] ?? '');
        $campaignName = $don['campaign_name'] ? htmlspecialchars($don['campaign_name'] ?? '') : 'General Donation';
        ?>
        <div class="modal">
            <div class="modal-header">
                <h2>Donation Details</h2>
                <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Donor Name</strong>
                        <div style="font-weight: 600;"><?php echo $donorName; ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Donor Email</strong>
                        <div style="font-weight: 600;"><?php echo $donorEmail; ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Campaign</strong>
                        <div style="font-weight: 600;"><?php echo $campaignName; ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Amount</strong>
                        <div style="font-weight: 800; color: var(--primary); font-size: 1.1rem;">₹<?php echo number_format($don['amount'], 2); ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Transaction ID</strong>
                        <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($don['transaction_id'] ?? ''); ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Payment Method</strong>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($don['payment_method'] ?? ''); ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Status</strong>
                        <div style="font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($don['payment_status'] ?? ''); ?></div>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Date & Time</strong>
                        <div style="font-weight: 600;"><?php echo date('M d, Y h:i A', strtotime($don['donation_date'])); ?></div>
                    </div>
                </div>
                
                <div style="background: rgba(0,0,0,0.03); padding: 15px; border-radius: 8px;">
                    <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">Donor Message</strong>
                    <p style="margin: 0; font-style: italic; color: var(--text-dark);">
                        <?php echo htmlspecialchars($don['donor_message'] ?: 'No message provided.'); ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-modal-close="true">Close</button>
                <?php if($don['pdf_path']): ?>
                    <a href="<?php echo htmlspecialchars($don['pdf_path'] ?? ''); ?>" target="_blank" class="btn-primary" style="text-decoration: none;"><i class="fas fa-download"></i> Download Receipt</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    exit;
}
// --- END AJAX MODAL HANDLER ---


$page_title = "Donation Management";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Donation Management</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Donations</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-primary" style="background: white; color: var(--text-dark); border: 1px solid rgba(0,0,0,0.1); text-decoration: none;">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </div>
            
            <!-- Financial Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Collected</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?php echo formatIndianCurrency($summary['total_collected'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Pending</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?php echo formatIndianCurrency($summary['total_pending'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Refunded</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?php echo formatIndianCurrency($summary['total_refunded'] ?? 0); ?></div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="glass-card" style="margin-bottom: 20px;">
                <form method="GET" action="ngo_donations.php" class="filter-bar">
                    <div class="filter-group">
                        <label>Search</label>
                        <input class="form-control" type="text" name="search" placeholder="Txn ID, Receipt, Name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>From Date</label>
                        <input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>To Date</label>
                        <input class="form-control" type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>

                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="ngo_donations.php" class="btn-primary" style="padding: 10px 20px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Donations Table -->
            <div class="glass-card">
                <?php if (empty($donations)): ?>
                    <?php render_empty_state('No Donations Found', 'No transactions match your search criteria.', 'fas fa-receipt'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Txn ID / Receipt</th>
                                    <th>Donor</th>
                                    <th>Campaign</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($donations as $don): ?>
                                <tr>
                                    <td>
                                        <div style="font-family: monospace; font-size: 0.9rem; color: var(--text-dark);"><?php echo htmlspecialchars($don['transaction_id'] ?? ''); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($don['receipt_number'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);">
                                            <?php echo $don['is_anonymous'] ? 'Anonymous' : htmlspecialchars($don['donor_name'] ?? ''); ?>
                                        </div>
                                        <?php if(!$don['is_anonymous']): ?>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($don['donor_email'] ?? ''); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($don['campaign_name'] ?? 'General Donation'); ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-dark);"><?php echo formatIndianCurrency($don['amount']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($don['payment_method'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($don['donation_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $statusColors = [
                                                'completed' => 'rgba(16,185,129,0.1)', 'pending' => 'rgba(245,158,11,0.1)',
                                                'failed' => 'rgba(239,68,68,0.1)', 'refunded' => 'rgba(107,114,128,0.1)'
                                            ];
                                            $textColors = [
                                                'completed' => 'var(--success)', 'pending' => 'var(--warning)',
                                                'failed' => 'var(--danger)', 'refunded' => 'var(--text-muted)'
                                            ];
                                        ?>
                                        <span class="badge" style="background: <?php echo $statusColors[$don['payment_status']]; ?>; color: <?php echo $textColors[$don['payment_status']]; ?>;">
                                            <?php echo ucfirst(htmlspecialchars($don['payment_status'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <!-- NGO Admins cannot edit financial records -->
                                            <button class="action-btn" style="opacity: 0.5; cursor: not-allowed;" title="View Only">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                            <?php if($don['pdf_path']): ?>
                                                <a href="<?php echo htmlspecialchars($don['pdf_path'] ?? ''); ?>" target="_blank" class="action-btn" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; text-decoration: none;" title="Download Receipt">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for($i=1; $i<=$totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

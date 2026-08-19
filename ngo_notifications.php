<?php
// admin_notifications.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only NGO Admin (Role ID 2) can access
Middleware::role([2]);

$pdo = getDatabase();

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_broadcast') {
            $title = htmlspecialchars(trim($_POST['title']));
            $message = htmlspecialchars(trim($_POST['message']));
            $target_audience = $_POST['target'];
            $type = 'system';
            
            if ($title && $message && $target_audience) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type) VALUES (?, ?, ?, ?, ?)");
                    $count = 0;
                    
                    if ($target_audience === 'all') {
                        $error_msg = "NGO Admins cannot broadcast to all users globally.";
                    } elseif (strpos($target_audience, 'role_') === 0) {
                        $role_id = (int) str_replace('role_', '', $target_audience);
                        $users = $pdo->prepare("SELECT id, role_id FROM users WHERE role_id = ?");
                        $users->execute([$role_id]);
                        $users = $users->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($users as $u) {
                            $stmt->execute([$u['id'], $u['role_id'], $title, $message, $type]);
                            $count++;
                        }
                        $pdo->commit();
                        $success_msg = "Broadcast sent successfully to $count users.";
                    } else if (strpos($target_audience, 'user_') === 0) {
                        $uid = (int) str_replace('user_', '', $target_audience);
                        $uq = $pdo->prepare("SELECT role_id FROM users WHERE id = ?");
                        $uq->execute([$uid]);
                        $r_id = $uq->fetchColumn();
                        
                        $stmt->execute([$uid, $r_id, $title, $message, $type]);
                        $pdo->commit();
                        $success_msg = "Broadcast sent successfully.";
                    } else {
                        $error_msg = "Invalid target audience selected.";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Failed to send broadcast: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($action === 'delete_notification') {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            if ($id) {
                $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
                $success_msg = "Notification deleted.";
            }
        }
    }
}

// Fetch Roles for dropdown
$roles = $pdo->query("SELECT * FROM roles WHERE name NOT IN ('Super Admin')")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent notifications sent to users (for display in admin view, we might just show recent system notifications)
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// To display a manageable list, we group by title/message since broadcasts create many rows
$countStmt = $pdo->query("SELECT COUNT(DISTINCT title, message, created_at) FROM notifications WHERE notification_type = 'system'");
$totalGroups = $countStmt->fetchColumn();
$totalPages = ceil($totalGroups / $limit);

$query = "SELECT title, message, notification_type, created_at, COUNT(id) as recipient_count, SUM(read_status) as read_count 
          FROM notifications 
          WHERE notification_type = 'system' 
          GROUP BY title, message, created_at 
          ORDER BY created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php 
$page_title = "Notifications & Communications";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Notifications & Communications</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Notifications</span>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success_msg): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <div class="layout-grid">
                <!-- Send Form -->
                <div>
                    <div class="form-card">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">Use this tool to communicate with Donors and Volunteers regarding your campaigns and events.</p>
                        <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-paper-plane" style="color: var(--primary);"></i> Send Broadcast
                        </h3>
                        <form method="POST" action="ngo_notifications.php" onsubmit="return confirm('Are you sure you want to send this broadcast?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="action" value="send_broadcast">
                            
                            <div class="form-group">
                                <label>Target Audience *</label>
                                <select class="form-control" name="target" required>
                                    <option value="">Select Audience...</option>
                                    <optgroup label="By Role">
                                        <?php foreach($roles as $role): ?>
                                            <option value="role_<?php echo $role['id'] ?? ''; ?>">All <?php echo htmlspecialchars($role['name'] ?? ''); ?>s</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Notification Title *</label>
                                <input class="form-control" type="text" name="title" required placeholder="e.g., Important Platform Update">
                            </div>
                            
                            <div class="form-group">
                                <label>Message *</label>
                                <textarea class="form-control" name="message" required placeholder="Write your message here..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-paper-plane"></i> Send Notification
                            </button>
                        </form>
                    </div>
                </div>

                <!-- History -->
                <div>
                    <div class="form-card" style="padding: 0;">
                        <div style="padding: 20px 25px; border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <h3 style="margin: 0; color: var(--text-dark);">Broadcast History</h3>
                        </div>
                        
                        <?php if(empty($broadcasts)): ?>
                            <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                                <i class="fas fa-history" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px;"></i>
                                <p>No broadcasts have been sent yet.</p>
                            </div>
                        <?php else: ?>
                            <div>
                                <?php foreach($broadcasts as $bc): ?>
                                    <div class="broadcast-item">
                                        <div class="broadcast-icon">
                                            <i class="fas fa-bullhorn"></i>
                                        </div>
                                        <div class="broadcast-content">
                                            <div class="broadcast-title"><?php echo htmlspecialchars($bc['title'] ?? ''); ?></div>
                                            <div class="broadcast-meta">
                                                <span><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($bc['created_at'])); ?></span>
                                                <span><i class="fas fa-users"></i> Sent to <?php echo $bc['recipient_count'] ?? ''; ?> users</span>
                                                <span style="color: var(--success);"><i class="fas fa-check-double"></i> <?php echo $bc['read_count'] ?? ''; ?> read</span>
                                            </div>
                                            <div class="broadcast-text">
                                                <?php echo nl2br(htmlspecialchars($bc['message'] ?? '')); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php for($i=1; $i<=$totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

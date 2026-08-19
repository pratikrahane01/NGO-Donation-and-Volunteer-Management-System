<?php
// admin_user_activity.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Super Admin (Role ID 1) can access
Middleware::role([1]);

$pdo = getDatabase();
$user_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;

if (!$user_id) {
    header("Location: admin_users.php");
    exit;
}

try {
    // Fetch User Info
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: admin_users.php");
        exit;
    }

    // Fetch Activity Logs
    $logStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 100");
    $logStmt->execute([$user_id]);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $logs = [];
    $user = null;
}
?>
<?php 
$page_title = "User Activity";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Activity Logs</h1>
                    <div class="breadcrumb">
                        <a href="admin_dashboard.php" style="color: var(--text-muted); text-decoration: none;">Dashboard</a>
                        <span style="margin: 0 8px;">/</span>
                        <a href="admin_users.php" style="color: var(--text-muted); text-decoration: none;">Users</a>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Activity</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="admin_users.php" class="btn-primary" style="background: white; color: var(--text-dark); border: 1px solid rgba(0,0,0,0.1); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="glass-card" style="margin-bottom: 20px; display: flex; gap: 20px; align-items: center;">
                <div style="width: 60px; height: 60px; background: rgba(124,154,134,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem;">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.2rem; color: var(--text-dark);"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></h2>
                    <p style="margin: 5px 0 0 0; color: var(--text-muted);"><?php echo htmlspecialchars($user['email'] ?? ''); ?> &bull; <?php echo htmlspecialchars($user['role_name'] ?? ''); ?></p>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="glass-card">
                <?php if (empty($logs)): ?>
                    <?php render_empty_state('No Activity Found', 'This user has not performed any actions yet.', 'fas fa-history'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                    <td>
                                        <span class="badge" style="background: rgba(0,0,0,0.05); color: var(--text-dark);">
                                            <?php echo htmlspecialchars($log['module'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 500; color: var(--text-dark);"><?php echo htmlspecialchars($log['action'] ?? ''); ?></td>
                                    <td style="font-family: monospace; color: var(--text-muted);"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

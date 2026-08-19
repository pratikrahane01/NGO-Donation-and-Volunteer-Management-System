<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/donor_queries.php';

Middleware::role([3]);

$pdo = getDatabase();
$donor_id = $_SESSION['user_id'];

// Handle Mark as Read Actions
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE recipient_id = ? AND role_id = ?");
        $stmt->execute([$donor_id, $_SESSION['role_id']]);
    } elseif ($_GET['action'] == 'mark_read' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND recipient_id = ? AND role_id = ?");
        $stmt->execute([$_GET['id'], $donor_id, $_SESSION['role_id']]);
    }
    // Redirect to clean URL
    header("Location: donor_notifications.php");
    exit;
}

$notifications = getDonorAllNotifications($pdo, $donor_id);
?>
<?php 
$page_title = "My Notifications";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="page-title">
                    <h1>Notifications</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Stay updated on your supported campaigns and account activity.</p>
                </div>
                <div>
                    <a href="donor_notifications.php?action=mark_all_read" class="btn-secondary" style="text-decoration: none;">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                </div>
            </div>

            <div class="glass-card">
                <?php if (empty($notifications)): ?>
                    <div style="padding: 40px;">
                        <?php render_empty_state('No Notifications', 'You\'re all caught up.', 'far fa-bell-slash'); ?>
                    </div>
                <?php else: ?>
                    <?php foreach($notifications as $notif): 
                        $isUnread = ($notif['read_status'] == 0);
                        $bg = $isUnread ? 'rgba(59,130,246,0.03)' : 'transparent';
                        $border = $isUnread ? 'border-left: 3px solid var(--primary);' : 'border-left: 3px solid transparent;';
                    ?>
                    <div style="padding: 20px; border-bottom: 1px solid #f1f5f9; background: <?php echo $bg; ?>; <?php echo $border; ?> display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; gap: 20px;">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: <?php echo $isUnread ? 'var(--primary)' : '#e2e8f0'; ?>; color: <?php echo $isUnread ? 'white' : 'var(--text-muted)'; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: var(--text-dark); font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($notif['title'] ?? ''); ?>
                                </h4>
                                <p style="margin: 0 0 10px 0; color: var(--text-body); font-size: 0.95rem; line-height: 1.5;">
                                    <?php echo htmlspecialchars($notif['message'] ?? ''); ?>
                                </p>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">
                                    <i class="far fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?>
                                    &bull; <strong><?php echo htmlspecialchars($notif['notification_type'] ?? ''); ?></strong>
                                </span>
                            </div>
                        </div>
                        <?php if ($isUnread): ?>
                            <div>
                                <a href="donor_notifications.php?action=mark_read&id=<?php echo $notif['id'] ?? ''; ?>" title="Mark as Read" style="color: var(--primary); width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-decoration: none;">
                                    <i class="fas fa-check"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

<?php
// volunteer_notifications.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];
$success_msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND recipient_id = ? AND role_id = ?");
        $stmt->execute([(int)$_POST['notification_id'], $volunteer_id, $_SESSION['role_id']]);
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE recipient_id = ? AND role_id = ? AND read_status = 0");
        $stmt->execute([$volunteer_id, $_SESSION['role_id']]);
        $success_msg = "All notifications marked as read.";
    }
}

if (isset($_GET['action']) && ($_GET['action'] ?? '') === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE recipient_id = ? AND role_id = ? AND read_status = 0");
    $stmt->execute([$volunteer_id, $_SESSION['role_id']]);
    header("Location: volunteer_notifications.php");
    exit;
}

// Fetch all notifications
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_id = ? AND role_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$volunteer_id, $_SESSION['role_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<?php 
$page_title = "Notifications";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="page-title">
                    <h1>Notifications</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Stay updated with your latest assignments and news.</p>
                </div>
                <form method="POST">
                    <button type="submit" name="action" value="mark_all_read" class="btn-primary" style="background: white; color: var(--primary); border: 1px solid var(--primary);"><i class="fas fa-check-double"></i> Mark All as Read</button>
                </form>
            </div>

            <div class="glass-card" style="padding: 0;">
                <?php if ($success_msg): ?>
                    <div style="padding: 15px; background: rgba(16,185,129,0.1); color: var(--success); margin: 20px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <?php if (empty($notifications)): ?>
                    <div style="padding: 40px;">
                        <?php render_empty_state('No Notifications', 'You\'re all caught up.', 'far fa-bell-slash'); ?>
                    </div>
                <?php else: ?>
                    <?php foreach($notifications as $notif): 
                        $iconClass = 'icon-default';
                        $icon = 'fas fa-bell';
                        if (strtolower($notif['notification_type']) == 'event') { $iconClass = 'icon-event'; $icon = 'far fa-calendar-alt'; }
                        elseif (strtolower($notif['notification_type']) == 'system') { $iconClass = 'icon-system'; $icon = 'fas fa-cog'; }
                    ?>
                        <div class="notification-item <?php echo ($notif['read_status'] ?? '') == 0 ? 'unread' : ''; ?>">
                            <div class="notification-icon <?php echo $iconClass; ?>">
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <strong style="color: var(--text-dark); display: block; font-size: 1.05rem; margin-bottom: 5px;"><?php echo htmlspecialchars($notif['title'] ?? ''); ?></strong>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></span>
                                </div>
                                <p style="font-size: 0.95rem; color: var(--text-body); margin: 0 0 10px 0; line-height: 1.5;"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></p>
                                
                                <?php if ($notif['read_status'] == 0): ?>
                                    <form method="POST">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id'] ?? ''; ?>">
                                        <button type="submit" name="action" value="mark_read" style="background: none; border: none; color: var(--primary); font-size: 0.85rem; font-weight: 600; cursor: pointer; padding: 0;"><i class="fas fa-check"></i> Mark as read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

<?php
// coordinator_applications.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Event Coordinator (Role ID 5) can access
Middleware::role([5]);

$pdo = getDatabase();
$coordinator_id = $_SESSION['user_id'];

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

// Handle Approve/Reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } elseif (isset($_POST['action']) && isset($_POST['reg_id'])) {
        $action = $_POST['action'];
        $reg_id = filter_var($_POST['reg_id'], FILTER_VALIDATE_INT);
        
        if ($reg_id && in_array($action, ['approve', 'reject'])) {
            try {
                // Verify ownership of the event
                $check = $pdo->prepare("
                    SELECT vr.*, e.title as event_title, e.coordinator_id, u.full_name as volunteer_name
                    FROM volunteer_registrations vr
                    JOIN events e ON vr.event_id = e.id
                    JOIN users u ON vr.volunteer_id = u.id
                    WHERE vr.id = ? AND e.coordinator_id = ? AND vr.approval_status = 'pending'
                ");
                $check->execute([$reg_id, $coordinator_id]);
                $reg = $check->fetch(PDO::FETCH_ASSOC);
                
                if (!$reg) {
                    $error_msg = "Application not found or you don't have permission to modify it.";
                } else {
                    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
                    
                    $pdo->beginTransaction();
                    
                    // Update status
                    $update = $pdo->prepare("UPDATE volunteer_registrations SET approval_status = ? WHERE id = ?");
                    $update->execute([$new_status, $reg_id]);
                    
                    // Notifications and Logs
                    if ($action === 'approve') {
                        $notifMsg = "Your application for the event '{$reg['event_title']}' has been approved.";
                        $notifStmt = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type, read_status) VALUES (?, 4, 'Application Approved', ?, 'application_approved', 0)");
                        $notifStmt->execute([$reg['volunteer_id'], $notifMsg]);
                        
                        $logMsg = "Approved volunteer application for {$reg['volunteer_name']} in {$reg['event_title']}";
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (?, ?, 'Volunteer Applications', ?)");
                        $logStmt->execute([$coordinator_id, $logMsg, $_SERVER['REMOTE_ADDR']]);
                        
                        $success_msg = "Application approved successfully.";
                    } else {
                        $notifMsg = "Your application for the event '{$reg['event_title']}' has been rejected.";
                        $notifStmt = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type, read_status) VALUES (?, 4, 'Application Rejected', ?, 'application_rejected', 0)");
                        $notifStmt->execute([$reg['volunteer_id'], $notifMsg]);
                        
                        $logMsg = "Rejected volunteer application for {$reg['volunteer_name']} in {$reg['event_title']}";
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (?, ?, 'Volunteer Applications', ?)");
                        $logStmt->execute([$coordinator_id, $logMsg, $_SERVER['REMOTE_ADDR']]);
                        
                        $success_msg = "Application rejected.";
                    }
                    
                    $pdo->commit();
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Pending Applications
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT vr.id as reg_id, u.full_name as volunteer_name, u.email, 
               e.title as event_title, vr.registration_date 
        FROM volunteer_registrations vr
        JOIN users u ON vr.volunteer_id = u.id
        JOIN events e ON vr.event_id = e.id
        WHERE e.coordinator_id = ? AND vr.approval_status = 'pending'
        ORDER BY vr.registration_date ASC
    ");
    $stmt->execute([$coordinator_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Coordinator Applications Error: " . $e->getMessage());
}
?>
<?php 
$page_title = "Volunteer Applications";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Pending Applications</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Review volunteer requests for your events</p>
                </div>
            </div>

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

            <div class="glass-card">
                <?php if (empty($applications)): ?>
                    <?php render_empty_state('No Pending Applications', 'You have no pending volunteer requests to review.', 'fas fa-envelope-open-text'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Volunteer</th>
                                    <th>Event</th>
                                    <th>Applied On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-dark); display: block;"><?php echo htmlspecialchars($app['volunteer_name'] ?? ''); ?></strong>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($app['email'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 600;"><?php echo htmlspecialchars($app['event_title'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                                            <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y g:i A', strtotime($app['registration_date'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <form method="POST" action="coordinator_applications.php" onsubmit="return confirm('Are you sure you want to approve this application?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="reg_id" value="<?php echo $app['reg_id'] ?? ''; ?>">
                                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success); border: none;">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="coordinator_applications.php" onsubmit="return confirm('Are you sure you want to reject this application?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="reg_id" value="<?php echo $app['reg_id'] ?? ''; ?>">
                                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--danger); border: none;">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

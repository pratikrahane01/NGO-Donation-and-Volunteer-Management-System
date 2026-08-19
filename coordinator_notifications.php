<?php
// coordinator_notifications.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Event Coordinator (Role ID 5) can access
Middleware::role([5]);

$pdo = getDatabase();

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

$coordinator_id = $_SESSION['user_id'];

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
                    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type) VALUES (?, 4, ?, ?, ?)");
                    $count = 0;
                    
                    if ($target_audience === 'all_my_volunteers') {
                        // All approved volunteers across all assigned events
                        $q = $pdo->prepare("
                            SELECT DISTINCT vr.volunteer_id 
                            FROM volunteer_registrations vr 
                            JOIN events e ON vr.event_id = e.id 
                            WHERE e.coordinator_id = ? AND vr.approval_status = 'approved'
                        ");
                        $q->execute([$coordinator_id]);
                        $users = $q->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($users as $uid) {
                            $stmt->execute([$uid, $title, $message, $type]);
                            $count++;
                        }
                        $pdo->commit();
                        $success_msg = "Notification sent successfully to $count volunteers.";
                        
                    } elseif (strpos($target_audience, 'event_') === 0) {
                        $event_id = (int) str_replace('event_', '', $target_audience);
                        
                        // Verify ownership of this event
                        $verify = $pdo->prepare("SELECT coordinator_id FROM events WHERE id = ?");
                        $verify->execute([$event_id]);
                        $owner_id = $verify->fetchColumn();
                        
                        if ($owner_id != $coordinator_id) {
                            $error_msg = "You do not have permission to send notifications for this event.";
                            $pdo->rollBack();
                        } else {
                            $q = $pdo->prepare("SELECT DISTINCT volunteer_id FROM volunteer_registrations WHERE event_id = ? AND approval_status = 'approved'");
                            $q->execute([$event_id]);
                            $users = $q->fetchAll(PDO::FETCH_COLUMN);
                            
                            foreach ($users as $uid) {
                                $stmt->execute([$uid, $title, $message, $type]);
                                $count++;
                            }
                            $pdo->commit();
                            $success_msg = "Notification sent successfully to $count participants.";
                        }
                    } else {
                        $error_msg = "Invalid target audience selected.";
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error_msg = "Failed to send notification: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        }
    }
}

// Fetch active events for the target dropdown
$eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE coordinator_id = ? AND status != 'completed' ORDER BY event_date ASC");
$eventsStmt->execute([$coordinator_id]);
$myEvents = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php 
$page_title = "Event Notifications";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 5px;">Communication</p>
                    <h1>Event Notifications</h1>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success" style="padding: 15px; background: rgba(39, 174, 96, 0.1); color: #27ae60; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-danger" style="padding: 15px; background: rgba(231, 76, 60, 0.1); color: #e74c3c; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                <!-- Compose Notification Form -->
                <div class="glass-card" style="flex: 1; min-width: 400px; height: fit-content;">
                    <div class="card-header">
                        <h3 class="card-title">Compose Notification</h3>
                    </div>
                    
                    <form method="POST" action="coordinator_notifications.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="send_broadcast">
                        
                        <div class="form-group">
                            <label>Target Audience *</label>
                            <select class="form-control" name="target" required>
                                <option value="">Select Audience...</option>
                                <option value="all_my_volunteers">All My Volunteers (All Events)</option>
                                <optgroup label="Specific Events">
                                    <?php foreach ($myEvents as $event): ?>
                                        <option value="event_<?php echo $event['id'] ?? ''; ?>">
                                            Event: <?php echo htmlspecialchars($event['title'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 5px;">Notifications will only be sent to volunteers with an 'approved' status.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Subject / Title *</label>
                            <input class="form-control" type="text" name="title" required placeholder="e.g. Change in Venue Location">
                        </div>
                        
                        <div class="form-group">
                            <label>Message Content *</label>
                            <textarea class="form-control" name="message" rows="5" required placeholder="Type your message here..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;">
                            <i class="fas fa-paper-plane"></i> Send Notification
                        </button>
                    </form>
                </div>
                
                <!-- Information Panel -->
                <div class="glass-card" style="flex: 1; min-width: 300px; height: fit-content; background: rgba(124,154,134,0.05); border: 1px solid rgba(124,154,134,0.2);">
                    <div class="card-header">
                        <h3 class="card-title">Notification Guidelines</h3>
                    </div>
                    <div style="color: var(--text-dark); line-height: 1.6; font-size: 0.9rem;">
                        <p style="margin-bottom: 15px;"><strong>Best Practices for Coordinators:</strong></p>
                        <ul style="padding-left: 20px; margin-bottom: 15px;">
                            <li style="margin-bottom: 10px;">Send reminders 24-48 hours before an event begins.</li>
                            <li style="margin-bottom: 10px;">Keep messages concise and focused on actionable information.</li>
                            <li style="margin-bottom: 10px;">Include critical details like time changes, attire requirements, or parking instructions.</li>
                            <li style="margin-bottom: 10px;">Only broadcast to "All My Volunteers" for general announcements that apply globally.</li>
                        </ul>
                        <div style="padding: 15px; background: white; border-radius: 8px; border-left: 4px solid var(--primary);">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i> Volunteers will see these notifications immediately in their personal dashboard when they log in.
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

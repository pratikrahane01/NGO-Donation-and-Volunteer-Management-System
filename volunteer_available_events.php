<?php
// volunteer_available_events.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

// Handle Registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } elseif (isset($_POST['register_event_id'])) {
        $event_id = filter_var($_POST['register_event_id'], FILTER_VALIDATE_INT);
        
        if ($event_id) {
            try {
                // Verify event exists and registration is open
                $checkEvent = $pdo->prepare("
                    SELECT title, max_volunteers 
                    FROM events 
                    WHERE id = ? AND status IN ('upcoming', 'ongoing') 
                    AND (registration_deadline IS NULL OR registration_deadline > NOW())
                ");
                $checkEvent->execute([$event_id]);
                $event = $checkEvent->fetch(PDO::FETCH_ASSOC);
                
                if (!$event) {
                    $error_msg = "This event is no longer available for registration.";
                } else {
                    // Check for duplicate registration
                    $checkDup = $pdo->prepare("SELECT id FROM volunteer_registrations WHERE volunteer_id = ? AND event_id = ?");
                    $checkDup->execute([$volunteer_id, $event_id]);
                    
                    if ($checkDup->rowCount() > 0) {
                        $error_msg = "You have already applied or registered for this event.";
                    } else {
                        $pdo->beginTransaction();
                        
                        // Insert application
                        $stmt = $pdo->prepare("
                            INSERT INTO volunteer_registrations (volunteer_id, event_id, approval_status, attendance_status) 
                            VALUES (?, ?, 'pending', 'registered')
                        ");
                        $stmt->execute([$volunteer_id, $event_id]);
                        
                        // Create notification for volunteer
                        $notifMsg = "Your application for the event '" . $event['title'] . "' has been submitted successfully and is pending approval.";
                        $notifStmt = $pdo->prepare("
                            INSERT INTO notifications (recipient_id, role_id, title, message, notification_type, read_status) 
                            VALUES (?, 4, 'Application Submitted', ?, 'event_registration', 0)
                        ");
                        $notifStmt->execute([$volunteer_id, $notifMsg]);
                        
                        $pdo->commit();
                        $success_msg = "Application Submitted Successfully.";
                    }
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

// Fetch Available Events
$events = [];
try {
    // Get active events that have not passed registration deadline
    // Also, get the count of registered (approved/pending) volunteers
    $stmt = $pdo->prepare("
        SELECT e.*, 
               u.full_name as coordinator_name,
               (SELECT COUNT(*) FROM volunteer_registrations vr WHERE vr.event_id = e.id AND vr.approval_status IN ('approved', 'pending')) as registered_count,
               (SELECT id FROM volunteer_registrations vr WHERE vr.event_id = e.id AND vr.volunteer_id = ?) as user_reg_id
        FROM events e
        JOIN users u ON e.coordinator_id = u.id
        WHERE e.status IN ('upcoming', 'ongoing')
          AND (e.registration_deadline IS NULL OR e.registration_deadline > NOW())
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$volunteer_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Volunteer Available Events Error: " . $e->getMessage());
}
?>
<?php 
$page_title = "Available Events";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Available Events</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Register for upcoming volunteer opportunities</p>
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

            <div class="event-grid">
                <?php if (empty($events)): ?>
                    <div style="grid-column: 1 / -1;">
                        <?php render_empty_state('No Available Events', 'There are no open events for registration at the moment.', 'far fa-calendar-times'); ?>
                    </div>
                <?php else: ?>
                    <?php foreach($events as $evt): ?>
                        <div class="event-card">
                            <div class="event-card-header">
                                <div class="event-card-title"><?php echo htmlspecialchars($evt['title'] ?? ''); ?></div>
                                <div class="event-card-desc"><?php echo htmlspecialchars($evt['description'] ?? ''); ?></div>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <div class="event-detail-item">
                                    <i class="far fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($evt['event_date'])); ?> at <?php echo date('g:i A', strtotime($evt['event_time'])); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($evt['venue'] ?? ''); ?></span>
                                </div>
                                <?php if ($evt['registration_deadline']): ?>
                                <div class="event-detail-item" style="color: var(--danger);">
                                    <i class="fas fa-hourglass-half" style="color: var(--danger);"></i>
                                    <span>Deadline: <?php echo date('M d, Y g:i A', strtotime($evt['registration_deadline'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="event-detail-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Status: <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($evt['status'] ?? ''); ?></strong></span>
                                </div>
                            </div>
                            
                            <div>
                                <?php 
                                    $req = (int)$evt['max_volunteers'];
                                    $reg = (int)$evt['registered_count'];
                                    $perc = $req > 0 ? min(100, round(($reg / $req) * 100)) : 100;
                                ?>
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px;">
                                    <span style="color: var(--text-muted);">Volunteers</span>
                                    <strong><?php echo $reg; ?> / <?php echo $req > 0 ? $req : 'Unlimited'; ?></strong>
                                </div>
                                <?php if ($req > 0): ?>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: <?php echo $perc; ?>%;"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: auto; padding-top: 10px;">
                                <?php if ($evt['user_reg_id']): ?>
                                    <button class="btn-primary" style="width: 100%; background: rgba(0,0,0,0.05); color: var(--text-muted); cursor: not-allowed;" disabled>
                                        <i class="fas fa-check"></i> Already Applied
                                    </button>
                                <?php elseif ($req > 0 && $reg >= $req): ?>
                                    <button class="btn-primary" style="width: 100%; background: rgba(239, 68, 68, 0.1); color: var(--danger); cursor: not-allowed;" disabled>
                                        <i class="fas fa-ban"></i> Event Full
                                    </button>
                                <?php else: ?>
                                    <form method="POST" action="volunteer_available_events.php" onsubmit="return confirm('Are you sure you want to register for this event?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="register_event_id" value="<?php echo $evt['id'] ?? ''; ?>">
                                        <button type="submit" class="btn-primary" style="width: 100%;">
                                            Register
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

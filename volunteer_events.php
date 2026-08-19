<?php
// volunteer_events.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];

// Fetch all assigned events
$events = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as coordinator_name, vr.approval_status, vr.attendance_status,
               (SELECT COUNT(*) FROM tasks t WHERE t.event_id = e.id AND t.volunteer_id = ?) as task_count
        FROM events e
        JOIN volunteer_registrations vr ON e.id = vr.event_id
        JOIN users u ON e.coordinator_id = u.id
        WHERE vr.volunteer_id = ? AND vr.approval_status = 'approved'
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$volunteer_id, $volunteer_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Volunteer Events Error: " . $e->getMessage());
}
?>
<?php 
$page_title = "My Events";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Assigned Events</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Events scheduled for you</p>
                </div>
            </div>

            <div class="glass-card">
                <?php if (empty($events)): ?>
                    <?php render_empty_state('No Events', 'No events have been assigned yet.', 'far fa-calendar-times'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Coordinator</th>
                                    <th>Date & Venue</th>
                                    <th>Attendance</th>
                                    <th>Task Count</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($events as $evt): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-dark); display: block;"><?php echo htmlspecialchars($evt['title'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--primary-dark);"><?php echo htmlspecialchars($evt['coordinator_name'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem; color: var(--text-dark); font-weight: 600;"><?php echo date('M d, Y', strtotime($evt['event_date'])); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['venue'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $attClass = 'status-pending'; // default
                                        if ($evt['attendance_status'] == 'attended' || ($evt['attendance_status'] ?? '') == 'present') $attClass = 'status-active';
                                        if ($evt['attendance_status'] == 'absent') $attClass = 'status-inactive';
                                        ?>
                                        <span class="status-badge <?php echo $attClass; ?>"><?php echo ucfirst($evt['attendance_status']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: rgba(0,0,0,0.05); color: var(--text-dark); font-weight: 600;">
                                            <?php echo (int)$evt['task_count']; ?> Tasks
                                        </span>
                                    </td>
                                    <td>
                                        <a href="volunteer_tasks.php" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">View Details</a>
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

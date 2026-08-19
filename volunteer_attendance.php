<?php
// volunteer_attendance.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/volunteer_queries.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $evt_id = filter_var($_POST['event_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if ($evt_id && in_array($action, ['check_in', 'check_out'])) {
        $stmt = $pdo->prepare("SELECT e.is_attendance_open, vr.id FROM events e JOIN volunteer_registrations vr ON e.id = vr.event_id WHERE e.id = ? AND vr.volunteer_id = ? AND vr.approval_status = 'approved'");
        $stmt->execute([$evt_id, $volunteer_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['is_attendance_open']) {
            $currentTime = date('Y-m-d H:i:s');
            if ($action === 'check_in') {
                $pdo->prepare("INSERT INTO attendance (volunteer_id, event_id, check_in, attendance_status) VALUES (?, ?, ?, 'present') ON DUPLICATE KEY UPDATE check_in = IF(check_in IS NULL, VALUES(check_in), check_in)")->execute([$volunteer_id, $evt_id, $currentTime]);
                $pdo->prepare("UPDATE volunteer_registrations SET attendance_status = 'attended' WHERE id = ?")->execute([$row['id']]);
                $success_msg = "Successfully checked in!";
            } else {
                $pdo->prepare("UPDATE attendance SET check_out = ? WHERE volunteer_id = ? AND event_id = ? AND check_out IS NULL")->execute([$currentTime, $volunteer_id, $evt_id]);
                $success_msg = "Successfully checked out!";
            }
        } else {
            $error_msg = "Attendance is not open for this event.";
        }
    }
}

// Fetch KPI for total hours
$dashboardData = getVolunteerDashboardData($pdo, $volunteer_id);
$kpis = $dashboardData['kpis'];

// Fetch active attendance sessions
$activeStmt = $pdo->prepare("
    SELECT e.id, e.title, a.check_in, a.check_out 
    FROM events e
    JOIN volunteer_registrations vr ON e.id = vr.event_id
    LEFT JOIN attendance a ON a.event_id = e.id AND a.volunteer_id = vr.volunteer_id
    WHERE vr.volunteer_id = ? AND vr.approval_status = 'approved' AND e.is_attendance_open = 1
");
$activeStmt->execute([$volunteer_id]);
$activeSessions = $activeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Attendance History
$attendance = get_volunteer_attendance_history($pdo, $volunteer_id, 50); // Get up to 50 records
?>
<?php 
$page_title = "My Attendance";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Hours contributed</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Attendance performance</p>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success" style="padding: 15px; background: rgba(39, 174, 96, 0.1); color: #27ae60; border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger" style="padding: 15px; background: rgba(231, 76, 60, 0.1); color: #e74c3c; border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
            <?php endif; ?>

            <?php if (!empty($activeSessions)): ?>
                <div class="glass-card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title" style="color: var(--primary);"><i class="fas fa-broadcast-tower"></i> Active Attendance Sessions</h3>
                    </div>
                    
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php foreach($activeSessions as $session): ?>
                            <div style="flex: 1; min-width: 300px; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                                <h4 style="margin: 0 0 10px 0;"><?php echo htmlspecialchars($session['title'] ?? ''); ?></h4>
                                
                                <?php if (empty($session['check_in'])): ?>
                                    <form method="POST">
                                        <input type="hidden" name="event_id" value="<?php echo $session['id'] ?? ''; ?>">
                                        <input type="hidden" name="action" value="check_in">
                                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fas fa-sign-in-alt"></i> Check In</button>
                                    </form>
                                <?php elseif (empty($session['check_out'])): ?>
                                    <div style="margin-bottom: 15px; color: var(--success); font-weight: 500;">
                                        <i class="fas fa-check-circle"></i> Checked in at <?php echo date('g:i A', strtotime($session['check_in'])); ?>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="event_id" value="<?php echo $session['id'] ?? ''; ?>">
                                        <input type="hidden" name="action" value="check_out">
                                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; background: var(--danger);"><i class="fas fa-sign-out-alt"></i> Check Out</button>
                                    </form>
                                <?php else: ?>
                                    <div style="color: var(--text-muted); font-weight: 500;">
                                        <i class="fas fa-calendar-check"></i> Session completed.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Total Hours Summary -->
            <div class="glass-card" style="margin-bottom: 2rem; display: flex; align-items: center; gap: 20px;">
                <div style="width: 60px; height: 60px; border-radius: 12px; background: rgba(124,154,134,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h3 style="margin: 0; color: var(--text-dark); font-size: 1.1rem;">Total Volunteering Hours</h3>
                    <div style="font-family: var(--font-stats); font-weight: 700; font-size: 2rem; color: var(--primary);">
                        <?php echo number_format($kpis['total_hours'], 1); ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">hrs</span>
                    </div>
                </div>
                
                <div style="margin-left: auto; width: 60px; height: 60px; border-radius: 12px; background: rgba(16,185,129,0.1); color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="fas fa-percentage"></i>
                </div>
                <div style="margin-right: 20px;">
                    <h3 style="margin: 0; color: var(--text-dark); font-size: 1.1rem;">Attendance Rate</h3>
                    <div style="font-family: var(--font-stats); font-weight: 700; font-size: 2rem; color: var(--success);">
                        <?php echo $kpis['attendance_percentage'] ?? ''; ?>%
                    </div>
                </div>
            </div>

            <div class="glass-card">
                <div class="card-header">
                    <h3 class="card-title">Attendance History</h3>
                </div>
                <?php if (empty($attendance)): ?>
                    <?php render_empty_state('No Records', 'No attendance records available.', 'fas fa-clipboard-list'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Event Date</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($attendance as $att): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-dark); display: block;"><?php echo htmlspecialchars($att['event_title'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 600;"><?php echo date('M d, Y', strtotime($att['event_date'])); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-sign-in-alt"></i> <?php echo $att['check_in'] ? date('g:i A', strtotime($att['check_in'])) : '-'; ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-sign-out-alt"></i> <?php echo $att['check_out'] ? date('g:i A', strtotime($att['check_out'])) : '-'; ?></span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary);"><?php echo ($att['hours'] ?? '') !== null ? number_format($att['hours'], 1) : '-'; ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-pending';
                                        if ($att['attendance_status'] == 'present') $statusClass = 'status-active';
                                        if ($att['attendance_status'] == 'absent') $statusClass = 'status-inactive';
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($att['attendance_status']); ?></span>
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

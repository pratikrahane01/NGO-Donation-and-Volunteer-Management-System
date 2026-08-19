<?php
// coordinator_attendance.php
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

// Controller Logic: Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'bulk_update_attendance') {
            $evt_id = filter_var($_POST['evt_id'], FILTER_VALIDATE_INT);
            $attendance_data = $_POST['attendance'] ?? [];
            
            if (!$evt_id) {
                $error_msg = "Please select an event.";
            } elseif (empty($attendance_data)) {
                $error_msg = "No volunteers selected to update.";
            } else {
                try {
                    // Check ownership
                    $check = $pdo->prepare("SELECT coordinator_id FROM events WHERE id = ?");
                    $check->execute([$evt_id]);
                    $owner_id = $check->fetchColumn();

                    if ($owner_id != $_SESSION['user_id']) {
                        $error_msg = "You do not have permission to manage attendance for this event.";
                    } else {
                        $pdo->beginTransaction();
                        
                        $stmtReg = $pdo->prepare("UPDATE volunteer_registrations SET attendance_status = ? WHERE id = ? AND event_id = ?");
                        $stmtAtt = $pdo->prepare("INSERT INTO attendance (volunteer_id, event_id, check_in, check_out, attendance_status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), attendance_status=VALUES(attendance_status)");
                        
                        foreach ($attendance_data as $reg_id => $data) {
                            $vol_id = filter_var($data['vol_id'], FILTER_VALIDATE_INT);
                            $status = htmlspecialchars($data['status'] ?? '');
                            $check_in = !empty($data['check_in']) ? $data['check_in'] : null;
                            $check_out = !empty($data['check_out']) ? $data['check_out'] : null;
                            
                            if ($vol_id && in_array($status, ['present', 'absent', 'late'])) {
                                $regStatus = ($status == 'present' || $status == 'late') ? 'attended' : 'absent';
                                $stmtReg->execute([$regStatus, $reg_id, $evt_id]);
                                $stmtAtt->execute([$vol_id, $evt_id, $check_in, $check_out, $status]);
                            }
                        }
                        
                        $pdo->commit();
                        $success_msg = "Attendance saved successfully.";
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error_msg = "Unable to save attendance. Please try again.";
                    error_log("Attendance Save Error: " . $e->getMessage());
                }
            }
        } elseif ($action === 'toggle_attendance') {
            $evt_id = filter_var($_POST['evt_id'], FILTER_VALIDATE_INT);
            if ($evt_id) {
                // Verify ownership
                $check = $pdo->prepare("SELECT coordinator_id, is_attendance_open FROM events WHERE id = ?");
                $check->execute([$evt_id]);
                $evtData = $check->fetch(PDO::FETCH_ASSOC);

                if ($evtData && ($evtData['coordinator_id'] ?? '') == $_SESSION['user_id']) {
                    $new_status = $evtData['is_attendance_open'] ? 0 : 1;
                    $pdo->prepare("UPDATE events SET is_attendance_open = ? WHERE id = ?")->execute([$new_status, $evt_id]);
                    $success_msg = $new_status ? "Attendance session opened for self-check-in." : "Attendance session closed.";
                } else {
                    $error_msg = "You do not have permission for this event.";
                }
            }
        }
    }
}

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$event_filter = isset($_GET['event_id']) ? filter_var($_GET['event_id'], FILTER_VALIDATE_INT) : '';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["e.coordinator_id = :coord_id", "vr.approval_status = 'approved'"];
$params = [':coord_id' => $_SESSION['user_id']];

if ($search !== '') {
    $whereClauses[] = "(u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$registrations = [];
$totalRegistrations = 0;
$totalPages = 1;
$allEvents = [];

try {
    // Fetch all events for the filter dropdown
    $eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE coordinator_id = ? ORDER BY event_date DESC");
    $eventsStmt->execute([$_SESSION['user_id']]);
    $allEvents = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($event_filter) {
        $eventDataStmt = $pdo->prepare("SELECT is_attendance_open FROM events WHERE id = ?");
        $eventDataStmt->execute([$event_filter]);
        $selectedEventData = $eventDataStmt->fetch(PDO::FETCH_ASSOC);
        $is_open = $selectedEventData ? (bool)$selectedEventData['is_attendance_open'] : false;

        $whereClauses[] = "vr.event_id = :event_id";
        $params[':event_id'] = $event_filter;
        
        $whereSQL = implode(" AND ", $whereClauses);

        // Total Count for Pagination
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM volunteer_registrations vr LEFT JOIN users u ON vr.volunteer_id = u.id LEFT JOIN events e ON vr.event_id = e.id WHERE $whereSQL");
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalRegistrations = $countStmt->fetchColumn();
        $totalPages = ceil($totalRegistrations / $limit);

        // Fetch Registrations with related data
        $query = "SELECT vr.*, 
                         u.full_name, u.email, u.phone,
                         e.title as event_title, e.event_date,
                         a.check_in, a.check_out, a.attendance_status as specific_attendance
                  FROM volunteer_registrations vr 
                  LEFT JOIN users u ON vr.volunteer_id = u.id 
                  LEFT JOIN events e ON vr.event_id = e.id 
                  LEFT JOIN attendance a ON vr.volunteer_id = a.volunteer_id AND vr.event_id = a.event_id
                  WHERE $whereSQL 
                  ORDER BY e.event_date DESC, u.full_name ASC 
                  LIMIT :limit OFFSET :offset";
                  
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>
<?php 
$page_title = "Attendance Tracking";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 5px;">Manage Participants</p>
                    <h1>Attendance Tracking</h1>
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

            <!-- Filter Bar -->
            <div class="glass-card" style="margin-bottom: 20px;">
                <form method="GET" action="coordinator_attendance.php" class="filter-bar" id="filterForm">
                    <input class="form-control" type="text" name="search" placeholder="Search volunteer..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select class="form-control" name="event_id" id="event_select" onchange="document.getElementById('loadingOverlay').style.display='flex'; document.getElementById('attendanceContainer').style.display='none'; this.form.submit();">
                        <option value="">Select an Event</option>
                        <?php foreach($allEvents as $ev): ?>
                            <option value="<?php echo $ev['id'] ?? ''; ?>" <?php echo $event_filter == $ev['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['title'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="coordinator_attendance.php" class="btn-primary" style="padding: 10px 20px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>
            
            <div id="loadingOverlay" style="display: none; justify-content: center; align-items: center; padding: 40px; text-align: center;">
                <div>
                    <i class="fas fa-circle-notch fa-spin fa-3x" style="color: var(--primary); margin-bottom: 15px;"></i>
                    <p style="color: var(--text-muted); font-weight: 500;">Loading Volunteers...</p>
                </div>
            </div>
            
            <?php if ($event_filter && isset($is_open)): ?>
                <div class="glass-card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">Attendance Session: 
                            <span class="status-badge <?php echo $is_open ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $is_open ? 'OPEN' : 'CLOSED'; ?>
                            </span>
                        </h3>
                        <p style="margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">When open, volunteers can self-check-in from their dashboard.</p>
                    </div>
                    <form method="POST" action="coordinator_attendance.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="toggle_attendance">
                        <input type="hidden" name="evt_id" value="<?php echo htmlspecialchars($event_filter); ?>">
                        <button type="submit" class="btn-primary" style="background: <?php echo $is_open ? 'var(--danger)' : 'var(--success)'; ?>; border: none; padding: 10px 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <i class="fas <?php echo $is_open ? 'fa-lock' : 'fa-unlock'; ?>"></i>
                            <?php echo $is_open ? 'Close Session' : 'Open Session'; ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="glass-card" id="attendanceContainer">
                <?php if (!$event_filter): ?>
                    <?php render_empty_state('Select an Event', 'Select an event from the dropdown above to begin attendance management.', 'fas fa-calendar-alt'); ?>
                <?php elseif (empty($registrations)): ?>
                    <?php render_empty_state('No Volunteers Found', 'No approved volunteers are assigned to this event.', 'fas fa-users-slash'); ?>
                <?php else: ?>
                    <form method="POST" action="coordinator_attendance.php" id="bulkAttendanceForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="bulk_update_attendance">
                        <input type="hidden" name="evt_id" value="<?php echo htmlspecialchars($event_filter); ?>">
                        <div class="table-responsive" style="margin-bottom: 20px;">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Volunteer</th>
                                        <th>Attendance Status</th>
                                        <th>Check In Time</th>
                                        <th>Check Out Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($reg['full_name'] ?? ''); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($reg['email'] ?? ''); ?></div>
                                            <input type="hidden" name="attendance[<?php echo $reg['id'] ?? ''; ?>][vol_id]" value="<?php echo $reg['volunteer_id'] ?? ''; ?>">
                                        </td>
                                        <td>
                                            <?php $att_status = $reg['specific_attendance'] ?: 'absent'; ?>
                                            <select class="form-control" name="attendance[<?php echo $reg['id'] ?? ''; ?>][status]" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-dark); width: 100%;">
                                                <option value="present" <?php echo $att_status == 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="late" <?php echo $att_status == 'late' ? 'selected' : ''; ?>>Late</option>
                                                <option value="absent" <?php echo $att_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="time" name="attendance[<?php echo $reg['id'] ?? ''; ?>][check_in]" value="<?php echo $reg['check_in'] ? date('H:i', strtotime($reg['check_in'])) : ''; ?>" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-dark);">
                                        </td>
                                        <td>
                                            <input type="time" name="attendance[<?php echo $reg['id'] ?? ''; ?>][check_out]" value="<?php echo $reg['check_out'] ? date('H:i', strtotime($reg['check_out'])) : ''; ?>" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-dark);">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-primary" id="saveAttendanceBtn">
                                <i class="fas fa-save"></i> Save Attendance
                            </button>
                        </div>
                    </form>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo urlencode($event_filter); ?>" 
                               style="padding: 8px 12px; border-radius: 6px; text-decoration: none; font-weight: 500;
                                      <?php echo $i == $page ? 'background: var(--primary); color: white;' : 'background: rgba(0,0,0,0.05); color: var(--text-dark);'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('bulkAttendanceForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const eventId = document.querySelector('input[name="evt_id"]').value;
                if (!eventId) {
                    e.preventDefault();
                    alert("Please select an event.");
                    return;
                }
                
                // Disable save button to prevent double submission and show processing state
                const btn = document.getElementById('saveAttendanceBtn');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    btn.disabled = true;
                }
            });
        }
    });
</script>
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

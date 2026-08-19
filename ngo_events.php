<?php
// admin_events.php
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

// Controller Logic: Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_event' || $action === 'edit_event') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $title = htmlspecialchars(trim($_POST['title']));
            $event_type = htmlspecialchars(trim($_POST['event_type']));
            $venue = htmlspecialchars(trim($_POST['venue']));
            $event_date = $_POST['event_date'];
            $event_time = $_POST['event_time'];
            $registration_deadline = $_POST['registration_deadline'] ?: null;
            $max_volunteers = filter_var($_POST['max_volunteers'], FILTER_VALIDATE_INT) ?: 0;
            $expected_budget = filter_var($_POST['expected_budget'], FILTER_VALIDATE_FLOAT) ?: 0;
            $coordinator_id = filter_var($_POST['coordinator_id'], FILTER_VALIDATE_INT);
            $status = $_POST['status'];
            $description = htmlspecialchars(trim($_POST['description']));

            if ($title && $venue && $event_date && $event_time && $coordinator_id) {
                try {
                    if ($action === 'create_event') {
                        $stmt = $pdo->prepare("INSERT INTO events (title, description, event_type, venue, event_date, event_time, registration_deadline, max_volunteers, expected_budget, coordinator_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$title, $description, $event_type, $venue, $event_date, $event_time, $registration_deadline, $max_volunteers, $expected_budget, $coordinator_id, $status]);
                        $success_msg = "Event created successfully.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE events SET title=?, description=?, event_type=?, venue=?, event_date=?, event_time=?, registration_deadline=?, max_volunteers=?, expected_budget=?, coordinator_id=?, status=? WHERE id=?");
                        $stmt->execute([$title, $description, $event_type, $venue, $event_date, $event_time, $registration_deadline, $max_volunteers, $expected_budget, $coordinator_id, $status, $id]);
                        $success_msg = "Event updated successfully.";
                    }
                } catch (PDOException $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($action === 'delete_event') {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Event deleted successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Cannot delete event as it has associated registrations or tasks.";
                }
            }
        }
    }
}

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(e.title LIKE :search OR e.venue LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($status_filter) {
    $whereClauses[] = "e.status = :status";
    $params[':status'] = $status_filter;
}

$whereSQL = implode(" AND ", $whereClauses);

try {
    // Event Statistics
    $statsStmt = $pdo->query("SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM events");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Total Count for Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM events e WHERE $whereSQL");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalEvents = $countStmt->fetchColumn();
    $totalPages = ceil($totalEvents / $limit);

    // Fetch Events with Registrations Count
    $query = "SELECT e.*, u.full_name as coordinator_name,
              (SELECT COUNT(*) FROM volunteer_registrations vr WHERE vr.event_id = e.id) as total_registrations
              FROM events e 
              LEFT JOIN users u ON e.coordinator_id = u.id 
              WHERE $whereSQL 
              ORDER BY e.event_date ASC 
              LIMIT :limit OFFSET :offset";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Coordinators (Users with roles that can coordinate)
    $coordStmt = $pdo->query("SELECT u.id, u.full_name, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name LIKE '%Coordinator%' OR r.name LIKE '%Admin%' ORDER BY u.full_name ASC");
    $coordinators = $coordStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
    $events = [];
    $coordinators = [];
    $stats = ['total_events'=>0, 'upcoming'=>0, 'completed'=>0];
    $totalPages = 1;
}



// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'event_form') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $event = null;
    
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    ?>
    <div class="modal">
        <div class="modal-header">
            <h2><?php echo $event ? 'Edit Event' : 'Create Event'; ?></h2>
            <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="<?php echo basename(__FILE__); ?>" class="ajax-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="<?php echo $event ? 'edit_event' : 'create_event'; ?>">
            <?php if($event): ?>
            <input type="hidden" name="id" value="<?php echo $event['id'] ?? ''; ?>">
            <?php endif; ?>
            
            <div class="modal-body">
                <div class="form-group full-width" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Event Title *</label>
                    <input class="form-control" type="text" name="title" value="<?php echo $event ? htmlspecialchars($event['title'] ?? '') : ''; ?>" required placeholder="e.g., Annual Beach Cleanup" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Event Type</label>
                    <input class="form-control" type="text" name="event_type" value="<?php echo $event ? htmlspecialchars($event['event_type'] ?? '') : ''; ?>" placeholder="e.g., Cleanup, Fundraiser" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Venue *</label>
                    <input class="form-control" type="text" name="venue" value="<?php echo $event ? htmlspecialchars($event['venue'] ?? '') : ''; ?>" required placeholder="Event location" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Date *</label>
                        <input class="form-control" type="date" name="event_date" value="<?php echo $event ? $event['event_date'] : ''; ?>" required style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Time *</label>
                        <input type="time" name="event_time" value="<?php echo $event ? $event['event_time'] : ''; ?>" required style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Max Volunteers</label>
                        <input class="form-control" type="number" name="max_volunteers" min="1" value="<?php echo $event ? ($event['max_volunteers'] == 0 ? '' : $event['max_volunteers']) : ''; ?>" placeholder="Leave empty for unlimited" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Coordinator</label>
                        <select class="form-control" name="coordinator_id" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                            <option value="">-- Select Coordinator --</option>
                            <?php 
                            $coord_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role_id = 5 AND status = 'active'");
                            while($c = $coord_stmt->fetch()) {
                                $selected = ($event && ($event['coordinator_id'] ?? '') == $c['id']) ? 'selected' : '';
                                echo "<option value='" . $c['id'] . "' $selected>" . htmlspecialchars($c['full_name'] ?? '') . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Status</label>
                        <select class="form-control" name="status" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                            <option value="upcoming" <?php echo ($event && ($event['status'] ?? '') == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="ongoing" <?php echo ($event && ($event['status'] ?? '') == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                            <option value="completed" <?php echo ($event && ($event['status'] ?? '') == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($event && ($event['status'] ?? '') == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Registration Deadline</label>
                        <input type="datetime-local" name="registration_deadline" value="<?php echo ($event && $event['registration_deadline']) ? date('Y-m-d\TH:i', strtotime($event['registration_deadline'])) : ''; ?>" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Expected Budget (₹)</label>
                        <input class="form-control" type="number" step="0.01" name="expected_budget" value="<?php echo $event ? $event['expected_budget'] : ''; ?>" placeholder="0.00" style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;">
                    </div>
                </div>

                <div class="form-group full-width" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Description *</label>
                    <textarea class="form-control" name="description" rows="4" required placeholder="Event details and instructions..." style="width: 100%; padding: 8px; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;"><?php echo $event ? htmlspecialchars($event['description'] ?? '') : ''; ?></textarea>
                </div>
            </div>
            
            <div class="modal-footer" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Event</button>
            </div>
        </form>
    </div>
    <?php
    exit;
}
// --- END AJAX MODAL HANDLER ---

?>
<?php 

$page_title = "Event Management";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Event Management</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Events</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button data-ajax-modal="true" data-url="ngo_events.php?modal=event_form" class="btn-primary"><i class="fas fa-plus"></i> Create Event</button>
                </div>
            </div>

            <!-- Event Statistics -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(124, 154, 134, 0.1); color: var(--primary);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Total Events</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?php echo $stats['total_events'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Upcoming Events</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?php echo $stats['upcoming'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Completed</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?php echo $stats['completed'] ?? 0; ?></div>
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

            <!-- Filter Bar -->
            <div class="glass-card" style="margin-bottom: 20px;">
                <form method="GET" action="ngo_events.php" class="filter-bar">
                    <input class="form-control" type="text" name="search" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select class="form-control" name="status">
                        <option value="">All Statuses</option>
                        <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>

                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="ngo_events.php" class="btn-primary" style="padding: 10px 20px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Events Table -->
            <div class="glass-card">
                <?php if (empty($events)): ?>
                    <?php render_empty_state('No Events Found', 'Create your first community event.', 'far fa-calendar-alt'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Event Details</th>
                                    <th>Date & Venue</th>
                                    <th>Coordinator</th>
                                    <th>Volunteers</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($events as $evt): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($evt['title'] ?? ''); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($evt['event_type'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <div style="color: var(--text-dark);"><i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($evt['event_date'])); ?> (<?php echo date('h:i A', strtotime($evt['event_time'])); ?>)</div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['venue'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($evt['coordinator_name'] ?? ''); ?></td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--primary);"><?php echo $evt['total_registrations'] ?? ''; ?></span> 
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">/ <?php echo $evt['max_volunteers'] ?: '∞'; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusColors = [
                                                'upcoming' => 'rgba(245,158,11,0.1)', 'ongoing' => 'rgba(59,130,246,0.1)',
                                                'completed' => 'rgba(16,185,129,0.1)', 'cancelled' => 'rgba(239,68,68,0.1)'
                                            ];
                                            $textColors = [
                                                'upcoming' => 'var(--warning)', 'ongoing' => '#3b82f6',
                                                'completed' => 'var(--success)', 'cancelled' => 'var(--danger)'
                                            ];
                                        ?>
                                        <span class="badge" style="background: <?php echo $statusColors[$evt['status']]; ?>; color: <?php echo $textColors[$evt['status']]; ?>;">
                                            <?php echo ucfirst(htmlspecialchars($evt['status'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button data-ajax-modal="true" data-url="ngo_events.php?modal=edit_event&id=<?php echo $evt['id'] ?? ''; ?>" class="action-btn" style="width: 32px; height: 32px;" title="Edit Event">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="ngo_events.php" onsubmit="return confirm('Are you sure you want to delete this event?');" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="action" value="delete_event">
                                                <input type="hidden" name="id" value="<?php echo $evt['id'] ?? ''; ?>">
                                                <button type="submit" class="action-btn" style="width: 32px; height: 32px; color: var(--danger);" title="Delete Event">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for($i=1; $i<=$totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

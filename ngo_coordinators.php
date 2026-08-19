<?php
// ngo_coordinators.php
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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_coordinator') {
            $full_name = htmlspecialchars(trim($_POST['full_name']));
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $phone = htmlspecialchars(trim($_POST['phone']));
            $password = $_POST['password'];
            
            if ($full_name && $email && $password) {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_msg = "Email already registered.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password, status) VALUES (5, ?, ?, ?, ?, 'active')");
                        $stmt->execute([$full_name, $email, $phone, $hashed_password]);
                        $success_msg = "Event Coordinator created successfully.";
                    } catch (PDOException $e) {
                        $error_msg = "Database error: " . $e->getMessage();
                    }
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($action === 'delete_coordinator') {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $pdo->prepare("DELETE FROM users WHERE id = ? AND role_id = 5")->execute([$id]);
                    $success_msg = "Coordinator deleted.";
                } catch (PDOException $e) {
                    $error_msg = "Cannot delete coordinator because they are assigned to events.";
                }
            }
        }
    }
}

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["u.role_id = 5"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSQL = implode(" AND ", $whereClauses);

try {
    // Total Count for Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalCoordinators = $countStmt->fetchColumn();
    $totalPages = ceil($totalCoordinators / $limit);

    // Fetch Coordinators
    $query = "SELECT u.*, COUNT(e.id) as assigned_events 
              FROM users u 
              LEFT JOIN events e ON u.id = e.coordinator_id 
              WHERE $whereSQL 
              GROUP BY u.id 
              ORDER BY u.created_at DESC 
              LIMIT :limit OFFSET :offset";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Failed to fetch coordinators: " . $e->getMessage();
    $coordinators = [];
    $totalPages = 1;
}

?>
<?php 

// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'edit_user') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $rolesStmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
        $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="modal">
            <div class="modal-header">
                <h2>Edit User Details</h2>
                <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
            </div>
            
            <form method="POST" action="<?php echo basename(__FILE__); ?>" class="ajax-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" value="<?php echo $user['id'] ?? ''; ?>">
                
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">User Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled style="width: 100%; padding: 10px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px; background: rgba(0,0,0,0.02);">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Role</label>
                        <select name="role_id" class="form-control" style="width: 100%; padding: 10px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;" <?php echo ($_SESSION['user_id'] ?? '') == $user['id'] ? 'disabled' : ''; ?>>
                            <?php foreach($roles as $r): ?>
                                <option value="<?php echo $r['id'] ?? ''; ?>" <?php echo ($user['role_id'] ?? '') == $r['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if($_SESSION['user_id'] == $user['id']): ?>
                            <input type="hidden" name="role_id" value="<?php echo $user['role_id'] ?? ''; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:5px; color:var(--text-dark); font-weight:600;">Status</label>
                        <select name="status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;" <?php echo ($_SESSION['user_id'] ?? '') == $user['id'] ? 'disabled' : ''; ?>>
                            <option value="active" <?php echo ($user['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($user['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($user['status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="banned" <?php echo ($user['status'] ?? '') == 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                        <?php if($_SESSION['user_id'] == $user['id']): ?>
                            <input type="hidden" name="status" value="<?php echo $user['status'] ?? ''; ?>">
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
        <?php
    }
    exit;
}
// --- END AJAX MODAL HANDLER ---


if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'create_coordinator') {
    ?>
    <div class="modal">
        <div class="modal-header">
            <h2>Add Event Coordinator</h2>
            <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="ngo_coordinators.php" class="ajax-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="create_coordinator">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input class="form-control" type="text" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <input class="form-control" type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input class="form-control" type="text" name="phone">
                </div>
                
                <div class="form-group">
                    <label>Temporary Password *</label>
                    <input class="form-control" type="password" name="password" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">They can change this after logging in.</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
                <button type="submit" class="btn-primary">Add Coordinator</button>
            </div>
        </form>
    </div>
    <?php
    exit;
}


if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'create_coordinator') {
    ?>
    <div class="modal">
        <div class="modal-header">
            <h2>Add Event Coordinator</h2>
            <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="ngo_coordinators.php" class="ajax-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="create_coordinator">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input class="form-control" type="text" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <input class="form-control" type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input class="form-control" type="text" name="phone">
                </div>
                
                <div class="form-group">
                    <label>Temporary Password *</label>
                    <input class="form-control" type="password" name="password" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">They can change this after logging in.</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
                <button type="submit" class="btn-primary">Add Coordinator</button>
            </div>
        </form>
    </div>
    <?php
    exit;
}


$page_title = "Coordinator Management";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Event Coordinators</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Coordinators</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button data-ajax-modal="true" data-url="ngo_coordinators.php?modal=create_coordinator" class="btn-primary"><i class="fas fa-plus"></i> Add Coordinator</button>
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
                <form method="GET" action="ngo_coordinators.php" class="filter-bar">
                    <input class="form-control" type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-search"></i> Search</button>
                    <a href="ngo_coordinators.php" class="btn-primary" style="padding: 10px 20px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Table -->
            <div class="glass-card">
                <?php if (empty($coordinators)): ?>
                    <?php render_empty_state('No Coordinators Found', 'Add an event coordinator to start assigning events.', 'fas fa-user-tie'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Info</th>
                                    <th>Status</th>
                                    <th>Assigned Events</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($coordinators as $coord): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($coord['full_name'] ?? ''); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">Joined <?php echo date('M Y', strtotime($coord['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div style="color: var(--text-dark);"><i class="fas fa-envelope" style="width:16px; color:var(--text-muted);"></i> <?php echo htmlspecialchars($coord['email'] ?? ''); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-phone" style="width:16px;"></i> <?php echo htmlspecialchars($coord['phone'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <?php if($coord['status'] === 'active'): ?>
                                            <span class="badge" style="background: rgba(16,185,129,0.1); color: var(--success);">Active</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(107,114,128,0.1); color: var(--text-muted);">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: var(--primary);"><?php echo $coord['assigned_events'] ?? ''; ?></span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <form method="POST" action="ngo_coordinators.php" onsubmit="return confirm('Delete this coordinator?');" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="action" value="delete_coordinator">
                                                <input type="hidden" name="id" value="<?php echo $coord['id'] ?? ''; ?>">
                                                <button type="submit" class="action-btn" style="color: var(--danger);" title="Delete">
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
        </div>

    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

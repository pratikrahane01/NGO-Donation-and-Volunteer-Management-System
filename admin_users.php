<?php
// admin_users.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Super Admin (Role ID 1) can access
Middleware::role([1]);

// Initialize Database
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
        
        if ($action === 'update_user') {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            $role_id = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);
            $status = htmlspecialchars($_POST['status'] ?? '');
            
            if ($user_id && $role_id && in_array($status, ['active', 'inactive', 'suspended', 'banned'])) {
                try {
                    // Prevent Super Admin from changing their own role/status from this UI
                    if ($user_id == $_SESSION['user_id']) {
                        $error_msg = "You cannot modify your own account status or role from this page.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET role_id = :role_id, status = :status WHERE id = :id");
                        $stmt->execute([
                            ':role_id' => $role_id,
                            ':status' => $status,
                            ':id' => $user_id
                        ]);
                        $success_msg = "User successfully updated.";
                        
                        // Log activity
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (:uid, :action, 'User Management', :ip)");
                        $logStmt->execute([
                            ':uid' => $_SESSION['user_id'],
                            ':action' => "Updated user ID $user_id (Role: $role_id, Status: $status)",
                            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                    }
                } catch (PDOException $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                }
            } else {
                $error_msg = "Invalid data provided.";
            }
        }
    }
}

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? filter_var($_GET['role'], FILTER_VALIDATE_INT) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($role_filter) {
    $whereClauses[] = "u.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}
if ($status_filter) {
    $whereClauses[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$whereSQL = implode(" AND ", $whereClauses);

try {
    // Total Count for Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);

    // Fetch Users
    $query = "SELECT u.*, r.name as role_name 
              FROM users u 
              LEFT JOIN roles r ON u.role_id = r.id 
              WHERE $whereSQL 
              ORDER BY u.created_at DESC 
              LIMIT :limit OFFSET :offset";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch All Roles for Dropdowns
    $rolesStmt = $pdo->query("SELECT * FROM roles ORDER BY name ASC");
    $allRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Failed to fetch users: " . $e->getMessage();
    $users = [];
    $allRoles = [];
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


$page_title = "User Management";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>User Management</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Users</span>
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
                <form method="GET" action="admin_users.php" class="filter-bar">
                    <input class="form-control" type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select class="form-control" name="role">
                        <option value="">All Roles</option>
                        <?php foreach($allRoles as $role): ?>
                            <option value="<?php echo $role['id'] ?? ''; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="form-control" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                    </select>

                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="admin_users.php" class="btn-primary" style="padding: 10px 20px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Users Table -->
            <div class="glass-card">
                <?php if (empty($users)): ?>
                    <?php render_empty_state('No Users Found', 'Try adjusting your search or filters.', 'fas fa-users-slash'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $u): ?>
                                <tr>
                                    <td>#<?php echo $u['id'] ?? ''; ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($u['full_name'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge" style="background: rgba(124, 154, 134, 0.1); color: var(--primary);">
                                            <?php echo htmlspecialchars($u['role_name'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusColors = [
                                                'active' => 'rgba(16,185,129,0.1)', 'inactive' => 'rgba(107,114,128,0.1)',
                                                'suspended' => 'rgba(245,158,11,0.1)', 'banned' => 'rgba(239,68,68,0.1)'
                                            ];
                                            $textColors = [
                                                'active' => 'var(--success)', 'inactive' => 'var(--text-muted)',
                                                'suspended' => 'var(--warning)', 'banned' => 'var(--danger)'
                                            ];
                                        ?>
                                        <span class="badge" style="background: <?php echo $statusColors[$u['status']]; ?>; color: <?php echo $textColors[$u['status']]; ?>;">
                                            <?php echo ucfirst(htmlspecialchars($u['status'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button data-ajax-modal="true" data-url="admin_users.php?modal=edit_user&id=<?php echo $u['id'] ?? ''; ?>" class="action-btn" >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="admin_user_activity.php?id=<?php echo $u['id'] ?? ''; ?>" class="action-btn" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; text-decoration: none;" title="View Activity Logs">
                                                <i class="fas fa-history"></i>
                                            </a>
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

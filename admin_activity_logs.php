<?php
// admin_activity_logs.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Super Admin (Role ID 1) can access
Middleware::role([1]);

$pdo = getDatabase();

// Fetch Roles for Filter
$rolesStmt = $pdo->query("SELECT id, name as role_name FROM roles ORDER BY id ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_id']) ? filter_var($_GET['role_id'], FILTER_VALIDATE_INT) : '';
$module_filter = isset($_GET['module']) ? trim($_GET['module']) : '';
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build Query
$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(u.full_name LIKE :search OR u.email LIKE :search OR al.description LIKE :search OR al.ip_address LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($role_filter) {
    $whereClauses[] = "u.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}
if ($module_filter) {
    $whereClauses[] = "al.module = :module";
    $params[':module'] = $module_filter;
}
if ($action_filter) {
    $whereClauses[] = "al.action = :action";
    $params[':action'] = $action_filter;
}
if ($start_date) {
    $whereClauses[] = "al.timestamp >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
}
if ($end_date) {
    $whereClauses[] = "al.timestamp <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
}

$whereSQL = implode(" AND ", $whereClauses);

try {
    // Total Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $whereSQL");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    // Fetch Logs
    $query = "SELECT al.*, al.timestamp as created_at, u.full_name, u.email, r.name as role_name 
              FROM activity_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              LEFT JOIN roles r ON u.role_id = r.id 
              WHERE $whereSQL 
              ORDER BY al.timestamp DESC 
              LIMIT :limit OFFSET :offset";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique modules and actions for filter dropdowns
    $modules = $pdo->query("SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
    $logs = [];
    $totalPages = 1;
    $modules = [];
    $actions = [];
}
?>
<?php 
$page_title = "Enterprise Activity Logs";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Activity Logs</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">System Logs</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" onclick="window.print()" style="background: white; color: var(--text-dark); border: 1px solid rgba(0,0,0,0.1);">
                        <i class="fas fa-print"></i> Export / Print
                    </button>
                </div>
            </div>

            <?php if (isset($error_msg)): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="glass-card" style="margin-bottom: 20px; padding: 15px 25px;">
                <form method="GET" action="admin_activity_logs.php" class="filter-bar">
                    <input class="form-control" type="text" name="search" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                    
                    <select class="form-control" name="role_id">
                        <option value="">All Roles</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?php echo $r['id'] ?? ''; ?>" <?php echo $role_filter == $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['role_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="form-control" name="module">
                        <option value="">All Modules</option>
                        <?php foreach($modules as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $module_filter === $m ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="form-control" name="action">
                        <option value="">All Actions</option>
                        <?php foreach($actions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $action_filter === $a ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" title="Start Date">
                    <input class="form-control" type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" title="End Date">

                    <button type="submit" class="btn-primary" style="padding: 8px 15px;"><i class="fas fa-filter"></i> Apply</button>
                    <a href="admin_activity_logs.php" class="btn-primary" style="padding: 8px 15px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="glass-card">
                <div style="margin-bottom: 15px; color: var(--text-muted); font-size: 0.9rem;">
                    Showing <?php echo number_format(count($logs)); ?> of <?php echo number_format($totalLogs); ?> logs
                </div>
                
                <?php if (empty($logs)): ?>
                    <?php render_empty_state('No Logs Found', 'No activity logs match your search criteria.', 'fas fa-history'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table log-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Module / Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td style="color: var(--text-muted);">
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?><br>
                                        <small><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if($log['full_name']): ?>
                                            <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($log['full_name'] ?? ''); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">System / Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($log['role_name']): ?>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($log['role_name'] ?? ''); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-module"><?php echo htmlspecialchars($log['module'] ?? ''); ?></span>
                                        <span class="badge-action" style="margin-left: 5px;"><?php echo htmlspecialchars($log['action'] ?? ''); ?></span>
                                    </td>
                                    <td style="max-width: 250px;">
                                        <?php echo htmlspecialchars($log['description'] ?? '-'); ?>
                                    </td>
                                    <td style="color: var(--text-muted);">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? ''); ?><br>
                                        <small title="<?php echo htmlspecialchars($log['user_agent'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($log['device'] ?? 'Unknown'); ?> - <?php echo htmlspecialchars($log['browser'] ?? 'Unknown'); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=1&search='.urlencode($search).'&role_id='.$role_filter.'&module='.$module_filter.'&action='.$action_filter.'&start_date='.$start_date.'&end_date='.$end_date.'" class="page-btn">1</a>';
                            if ($startPage > 2) echo '<span style="padding: 6px;">...</span>';
                        }
                        
                        for($i=$startPage; $i<=$endPage; $i++) {
                            $active = $i == $page ? 'active' : '';
                            echo '<a href="?page='.$i.'&search='.urlencode($search).'&role_id='.$role_filter.'&module='.$module_filter.'&action='.$action_filter.'&start_date='.$start_date.'&end_date='.$end_date.'" class="page-btn '.$active.'">'.$i.'</a>';
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<span style="padding: 6px;">...</span>';
                            echo '<a href="?page='.$totalPages.'&search='.urlencode($search).'&role_id='.$role_filter.'&module='.$module_filter.'&action='.$action_filter.'&start_date='.$start_date.'&end_date='.$end_date.'" class="page-btn">'.$totalPages.'</a>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

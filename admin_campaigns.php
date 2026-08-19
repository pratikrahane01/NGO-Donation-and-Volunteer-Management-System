<?php
// admin_campaigns.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Super Admin (Role ID 1) can access
Middleware::role([1]);

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
        
        if ($action === 'create_campaign' || $action === 'edit_campaign') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $name = htmlspecialchars(trim($_POST['name']));
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
            $target_amount = filter_var($_POST['target_amount'], FILTER_VALIDATE_FLOAT);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];
            $featured = isset($_POST['featured']) ? 1 : 0;
            $short_description = htmlspecialchars(trim($_POST['short_description']));
            $description = htmlspecialchars(trim($_POST['description']));

            if ($name && $category_id && $target_amount && $start_date && $end_date) {
                try {
                    if ($action === 'create_campaign') {
                        $stmt = $pdo->prepare("INSERT INTO campaigns (category_id, name, slug, short_description, description, target_amount, start_date, end_date, status, featured, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$category_id, $name, $slug, $short_description, $description, $target_amount, $start_date, $end_date, $status, $featured, $_SESSION['user_id']]);
                        $success_msg = "Campaign created successfully.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE campaigns SET category_id=?, name=?, slug=?, short_description=?, description=?, target_amount=?, start_date=?, end_date=?, status=?, featured=? WHERE id=?");
                        $stmt->execute([$category_id, $name, $slug, $short_description, $description, $target_amount, $start_date, $end_date, $status, $featured, $id]);
                        $success_msg = "Campaign updated successfully.";
                    }
                } catch (PDOException $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($action === 'delete_campaign') {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Campaign deleted successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Cannot delete campaign as it has associated donations or images.";
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
    $whereClauses[] = "(c.name LIKE :search OR c.short_description LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($status_filter) {
    $whereClauses[] = "c.status = :status";
    $params[':status'] = $status_filter;
}

$whereSQL = implode(" AND ", $whereClauses);

try {
    // Total Count for Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns c WHERE $whereSQL");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalCampaigns = $countStmt->fetchColumn();
    $totalPages = ceil($totalCampaigns / $limit);

    // Fetch Campaigns
    $query = "SELECT c.*, cat.name as category_name 
              FROM campaigns c 
              LEFT JOIN campaign_categories cat ON c.category_id = cat.id 
              WHERE $whereSQL 
              ORDER BY c.created_at DESC 
              LIMIT :limit OFFSET :offset";
              
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Categories
    $catStmt = $pdo->query("SELECT * FROM campaign_categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Failed to fetch campaigns: " . $e->getMessage();
    $campaigns = [];
    $categories = [];
    $totalPages = 1;
}

?>
<?php 
// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal'])) {
    if ($_GET['modal'] === 'campaign_form') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $campaign = null;
        
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
            $stmt->execute([$id]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $catStmt = $pdo->query("SELECT * FROM campaign_categories ORDER BY name ASC");
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="modal">
            <div class="modal-header">
                <h2><?php echo $campaign ? 'Edit Campaign' : 'Create Campaign'; ?></h2>
                <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="admin_campaigns.php" class="ajax-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="<?php echo $campaign ? 'edit_campaign' : 'create_campaign'; ?>">
                <?php if($campaign): ?>
                <input type="hidden" name="id" value="<?php echo $campaign['id'] ?? ''; ?>">
                <?php endif; ?>
                
                <div class="modal-body">
                    
                    <div class="form-section">
                        <div class="form-section-title">Campaign Details</div>
                        <div class="form-group">
                            <label class="form-label">Campaign Name *</label>
                            <input class="form-control" type="text" name="name" value="<?php echo $campaign ? htmlspecialchars($campaign['name'] ?? '') : ''; ?>" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Category *</label>
                                <select class="form-control" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id'] ?? ''; ?>" <?php echo ($campaign && ($campaign['category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Amount (₹) *</label>
                                <input class="form-control" type="number" step="0.01" name="target_amount" value="<?php echo $campaign ? htmlspecialchars($campaign['target_amount'] ?? '') : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Schedule</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input class="form-control" type="date" name="start_date" value="<?php echo $campaign ? $campaign['start_date'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input class="form-control" type="date" name="end_date" value="<?php echo $campaign ? $campaign['end_date'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Description</div>
                        <div class="form-group">
                            <label class="form-label">Short Description (for cards) *</label>
                            <textarea class="form-control" name="short_description" rows="2" maxlength="255" required><?php echo $campaign ? htmlspecialchars($campaign['short_description'] ?? '') : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Description (for details page)</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo $campaign ? htmlspecialchars($campaign['description'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Settings</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Status *</label>
                                <select class="form-control" name="status" required>
                                    <option value="draft" <?php echo ($campaign && ($campaign['status'] ?? '') == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="active" <?php echo ($campaign && ($campaign['status'] ?? '') == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo ($campaign && ($campaign['status'] ?? '') == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($campaign && ($campaign['status'] ?? '') == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: center;">
                                <label class="custom-checkbox" style="margin-top: 24px;">
                                    <input type="checkbox" name="featured" value="1" <?php echo ($campaign && $campaign['featured']) ? 'checked' : ''; ?>>
                                    <span>Featured Campaign (appears on Homepage)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Campaign</button>
                </div>
            </form>
        </div>
        <?php
        exit;
    }
}
// --- END AJAX MODAL HANDLER ---

$page_title = "Campaign Management";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Campaign Management</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Campaigns</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button data-ajax-modal="true" data-url="admin_campaigns.php?modal=campaign_form" class="btn-primary"><i class="fas fa-plus"></i> New Campaign</button>
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
                <form method="GET" action="admin_campaigns.php" class="filter-bar">
                    <input class="form-control" type="text" name="search" placeholder="Search campaigns..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select class="form-control" name="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>

                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="admin_campaigns.php" class="btn-primary" style="padding: 10px 20px; background: rgba(0,0,0,0.05); color: var(--text-dark); text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Campaigns Table -->
            <div class="glass-card">
                <?php if (empty($campaigns)): ?>
                    <?php render_empty_state('No Campaigns Found', 'Start your first fundraising campaign.', 'fas fa-bullhorn'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Category</th>
                                    <th>Goal / Raised</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Featured</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($campaigns as $camp): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($camp['name'] ?? ''); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('M d', strtotime($camp['start_date'])); ?> - <?php echo date('M d, Y', strtotime($camp['end_date'])); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($camp['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo formatIndianCurrency($camp['target_amount']); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--success);">Raised: <?php echo formatIndianCurrency($camp['collected_amount']); ?></div>
                                    </td>
                                    <td style="width: 150px;">
                                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark); text-align: right;"><?php echo $camp['goal_completed_percentage'] ?? ''; ?>%</div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill" style="width: <?php echo min(100, $camp['goal_completed_percentage']); ?>%;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusColors = [
                                                'active' => 'rgba(16,185,129,0.1)', 'draft' => 'rgba(107,114,128,0.1)',
                                                'completed' => 'rgba(59,130,246,0.1)', 'cancelled' => 'rgba(239,68,68,0.1)'
                                            ];
                                            $textColors = [
                                                'active' => 'var(--success)', 'draft' => 'var(--text-muted)',
                                                'completed' => '#3b82f6', 'cancelled' => 'var(--danger)'
                                            ];
                                        ?>
                                        <span class="badge" style="background: <?php echo $statusColors[$camp['status']]; ?>; color: <?php echo $textColors[$camp['status']]; ?>;">
                                            <?php echo ucfirst(htmlspecialchars($camp['status'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($camp['featured']): ?>
                                            <span style="color: #f59e0b;"><i class="fas fa-star"></i></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);"><i class="far fa-star"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button data-ajax-modal="true" data-url="admin_campaigns.php?modal=campaign_form&id=<?php echo $camp['id'] ?? ''; ?>" class="action-btn" style="width: 32px; height: 32px;" title="Edit Campaign">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="admin_campaigns.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this campaign?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="action" value="delete_campaign">
                                                <input type="hidden" name="id" value="<?php echo $camp['id'] ?? ''; ?>">
                                                <button type="submit" class="action-btn" style="width: 32px; height: 32px; color: var(--danger);" title="Delete Campaign">
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

<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';

// Super Admin only
Middleware::role([1]);

$pdo = getDatabase();

// Handle Approval Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['type'])) {
    $id = (int)$_POST['id'];
    $action = ($_POST['action'] ?? '') === 'approve' ? 'approved' : 'rejected';
    $type = $_POST['type'];

    try {
        if ($type === 'campaign') {
            $status = ($_POST['action'] ?? '') === 'approve' ? 'active' : 'cancelled';
            $stmt = $pdo->prepare("UPDATE campaigns SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        } elseif ($type === 'user') {
            $status = ($_POST['action'] ?? '') === 'approve' ? 'active' : 'banned';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }
        // Redirect to prevent form resubmission
        // (AJAX handles success)
        
    } catch (PDOException $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Fetch pending items
$pending_campaigns = [];
$pending_users = [];

try {
    $stmt = $pdo->query("SELECT id, name, created_at, 'campaign' as type FROM campaigns WHERE status = 'draft'");
    $pending_campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, full_name as name, created_at, 'user' as type FROM users WHERE status = 'inactive'");
    $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$all_pending = array_merge($pending_campaigns, $pending_users);
usort($all_pending, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>
<?php 

// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'approval_review') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    
    $name = '';
    $date = '';
    
    if ($type === 'campaign') {
        $stmt = $pdo->prepare("SELECT name, created_at, description FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $name = $row['name'];
            $date = $row['created_at'];
            $desc = $row['description'];
        }
    } else if ($type === 'user') {
        $stmt = $pdo->prepare("SELECT full_name as name, created_at, email as description FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $name = $row['name'];
            $date = $row['created_at'];
            $desc = $row['description'];
        }
    }
    
    if ($name) {
        ?>
        <div class="modal">
            <div class="modal-header">
                <h2>Review <?php echo ucfirst($type); ?></h2>
                <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <strong style="display:block; color: var(--text-muted); font-size: 0.85rem;">Name</strong>
                    <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($name); ?></div>
                </div>
                <div style="margin-bottom: 20px;">
                    <strong style="display:block; color: var(--text-muted); font-size: 0.85rem;">Details</strong>
                    <div style="background: rgba(0,0,0,0.02); padding: 10px; border-radius: 6px; font-size: 0.95rem;"><?php echo htmlspecialchars($desc); ?></div>
                </div>
                <div style="margin-bottom: 20px;">
                    <strong style="display:block; color: var(--text-muted); font-size: 0.85rem;">Submitted On</strong>
                    <div><?php echo date('M d, Y h:i A', strtotime($date)); ?></div>
                </div>
                
                <form method="POST" action="admin_approvals.php" class="ajax-form" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
                    <button type="submit" name="action" value="reject" class="btn-primary" style="background: var(--danger); border: none;"><i class="fas fa-times"></i> Reject</button>
                    <button type="submit" name="action" value="approve" class="btn-primary" style="background: var(--success); border: none;"><i class="fas fa-check"></i> Approve</button>
                </form>
            </div>
        </div>
        <?php
    }
    
}
// --- END AJAX MODAL HANDLER ---


$page_title = "Pending Approvals";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Pending Approvals</h1>
                </div>
            </div>
            
            <div class="glass-card">
                <?php if (isset($_GET['success'])): ?>
                    <div style="padding: 15px; background: rgba(16,185,129,0.1); color: var(--success); border-radius: 8px; margin-bottom: 20px;">Status updated successfully.</div>
                <?php endif; ?>

                <?php if (empty($all_pending)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 15px;"></i>
                        <h3>All caught up!</h3>
                        <p>There are no pending items requiring your approval.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Date Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($all_pending as $item): ?>
                                <tr>
                                    <td><span class="status-badge status-pending"><?php echo ucfirst($item['type']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($item['name'] ?? ''); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <button data-ajax-modal="true" data-url="admin_approvals.php?modal=approval_review&id=<?php echo $item['id'] ?? ''; ?>&type=<?php echo $item['type'] ?? ''; ?>" class="btn-primary" style="padding: 5px 15px; font-size: 0.85rem;"><i class="fas fa-search"></i> Review</button>
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

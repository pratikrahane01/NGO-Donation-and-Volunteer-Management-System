<?php
// admin_inquiry_detail.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/core/Logger.php';

// Allow Super Admin (1) and NGO Admin (2)
Middleware::role([1, 2]);

$pdo = getDatabase();
$role_id = $_SESSION['role_id'];

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: admin_inquiries.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_inquiry') {
            $status = $_POST['status'];
            $priority = $_POST['priority'];
            $internal_notes = htmlspecialchars(trim($_POST['internal_notes']));
            
            try {
                $stmt = $pdo->prepare("UPDATE contact_inquiries SET status = ?, priority = ?, internal_notes = ? WHERE inquiry_id = ?");
                $stmt->execute([$status, $priority, $internal_notes, $id]);
                
                $success_msg = "Inquiry updated successfully.";
                Logger::logActivity($pdo, $_SESSION['user_id'], $_SESSION['role_id'], 'Inquiries', 'Update Inquiry', "Updated inquiry ID {$id}");
            } catch (PDOException $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Inquiry
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as assigned_admin_name
        FROM contact_inquiries c
        LEFT JOIN users u ON c.assigned_admin_id = u.id
        WHERE c.inquiry_id = ?
    ");
    $stmt->execute([$id]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inquiry) {
        header("Location: admin_inquiries.php");
        exit;
    }
    
    // Auto mark as read if pending
    if ($inquiry['status'] === 'pending') {
        $updateStmt = $pdo->prepare("UPDATE contact_inquiries SET status = 'read' WHERE inquiry_id = ?");
        $updateStmt->execute([$id]);
        $inquiry['status'] = 'read';
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
$prefix = ($role_id == 2) ? 'ngo' : 'admin';
?>
<?php 
$page_title = "Inquiry Details";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header" style="margin-bottom: 20px;">
                <div class="page-title">
                    <h1>Inquiry Details</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?php echo $prefix; ?>_inquiries.php" style="color: var(--text-muted); text-decoration: none;">Contact Inquiries</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>#<?php echo $id; ?></span>
                    </div>
                </div>
                <div class="page-actions">
                    <a href="<?php echo $prefix; ?>_inquiries.php" class="btn-primary" style="background: var(--surface-hover); color: var(--text-dark); border: 1px solid rgba(0,0,0,0.1); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert-message alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-message alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                <!-- Main Details -->
                <div class="glass-card" style="flex: 2; min-width: 500px;">
                    <div class="card-header">
                        <h3 class="card-title">Message Content</h3>
                    </div>
                    <div style="padding: 1rem 0;">
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--text-dark); font-size: 1.1rem; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']); ?>
                            </h4>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($inquiry['email'] ?? ''); ?> 
                                <span style="margin: 0 10px;">|</span> 
                                <i class="far fa-clock"></i> <?php echo date('F j, Y, g:i A', strtotime($inquiry['submitted_at'])); ?>
                            </p>
                        </div>
                        
                        <div style="background: rgba(0,0,0,0.02); padding: 20px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05); font-size: 0.95rem; line-height: 1.6; color: var(--text-body); white-space: pre-wrap;"><?php echo htmlspecialchars($inquiry['message'] ?? ''); ?></div>
                    </div>
                </div>

                <!-- Admin Actions & Sidebar -->
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Management</h3>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="update_inquiry">
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="pending" <?php echo ($inquiry['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="read" <?php echo ($inquiry['status'] ?? '') == 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="resolved" <?php echo ($inquiry['status'] ?? '') == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low" <?php echo ($inquiry['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($inquiry['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($inquiry['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Internal Notes</label>
                            <textarea name="internal_notes" class="form-control" rows="4" placeholder="Add notes for internal team..."><?php echo htmlspecialchars($inquiry['internal_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Inquiry</button>
                    </form>
                </div>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

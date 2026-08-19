<?php
// ngo_inquiries.php
// Wrapper for NGO Admin (Role 2) to use the same functionality as Super Admin
require_once __DIR__ . '/admin_inquiries.php';
?>
<?php
// --- AJAX MODAL HANDLER ---
if (isset($_GET['modal']) && ($_GET['modal'] ?? '') === 'inquiry_details') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as assigned_admin_name
        FROM contact_inquiries c
        LEFT JOIN users u ON c.assigned_admin_id = u.id
        WHERE c.inquiry_id = ?
    ");
    $stmt->execute([$id]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($inquiry) {
        // Auto mark as read if pending
        if ($inquiry['status'] === 'pending') {
            $updateStmt = $pdo->prepare("UPDATE contact_inquiries SET status = 'read' WHERE inquiry_id = ?");
            $updateStmt->execute([$id]);
            $inquiry['status'] = 'read';
        }
        ?>
        <div class="modal">
            <div class="modal-header">
                <h2>Inquiry Details</h2>
                <button type="button" class="close-btn" data-modal-close="true"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div style="background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);">
                    <h4 style="margin-bottom: 5px; color: var(--text-dark);"><?php echo htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']); ?></h4>
                    <p style="margin-bottom: 10px; font-size: 0.9rem; color: var(--text-muted);">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($inquiry['email'] ?? ''); ?> | 
                        <i class="far fa-clock"></i> <?php echo date('F j, Y, g:i A', strtotime($inquiry['submitted_at'])); ?>
                    </p>
                    <div style="font-size: 0.95rem; line-height: 1.6; color: var(--text-body); white-space: pre-wrap;"><?php echo htmlspecialchars($inquiry['message'] ?? ''); ?></div>
                </div>

                <form method="POST" action="<?php echo basename(__FILE__); ?>" class="ajax-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="action" value="update_inquiry">
                    <input type="hidden" name="id" value="<?php echo $inquiry['inquiry_id'] ?? ''; ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="pending" <?php echo ($inquiry['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="read" <?php echo ($inquiry['status'] ?? '') == 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="resolved" <?php echo ($inquiry['status'] ?? '') == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low" <?php echo ($inquiry['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($inquiry['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($inquiry['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Internal Notes (Optional)</label>
                        <textarea name="internal_notes" class="form-control" rows="3"><?php echo htmlspecialchars($inquiry['internal_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="modal-footer" style="padding: 15px 0 0 0; border: none; margin-top: 10px;">
                        <button type="button" class="btn-secondary" data-modal-close="true">Close</button>
                        <button type="submit" class="btn btn-primary">Update Inquiry</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    exit;
}
// --- END AJAX MODAL HANDLER ---

// --- AJAX MODAL POST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] ?? '') === 'update_inquiry') {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $internal_notes = htmlspecialchars(trim($_POST['internal_notes']));
        
        try {
            $stmt = $pdo->prepare("UPDATE contact_inquiries SET status = ?, priority = ?, internal_notes = ? WHERE inquiry_id = ?");
            $stmt->execute([$status, $priority, $internal_notes, $id]);
            // If ajax, this will just reload the page/table via the JS handler
        } catch (PDOException $e) {
            // handle error
        }
    }
}
// -------------------------------



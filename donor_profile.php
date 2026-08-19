<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

Middleware::role([3]);

$pdo = getDatabase();
$donor_id = $_SESSION['user_id'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if (empty($full_name)) {
        $error = "Full Name is required.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, address = ?, bio = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if ($stmt->execute([$full_name, $phone, $address, $bio, $donor_id])) {
            $_SESSION['full_name'] = $full_name; // update session
            $message = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    }
}

// Fetch current details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$donor_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<?php 
$page_title = "My Profile";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <div class="page-header">
                <div class="page-title">
                    <h1>My Profile</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Manage your account details and preferences.</p>
                </div>
            </div>

            <div style="max-width: 800px;">
                <?php if ($message): ?>
                    <div style="background: rgba(16,185,129,0.1); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div style="background: rgba(239,68,68,0.1); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card">
                    <form action="" method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;">(Cannot be changed here)</span></label>
                                <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly style="background: #f1f5f9; cursor: not-allowed;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="address">Mailing Address</label>
                                <input type="text" name="address" id="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="For tax receipts">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 30px;">
                            <label for="bio">About Me (Optional)</label>
                            <textarea name="bio" id="bio" class="form-control" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <button type="submit" class="btn-primary" style="padding: 12px 25px;">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

<?php
// volunteer_profile.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, bio = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $bio, $volunteer_id]);
            $_SESSION['full_name'] = $full_name; // Update session
            $success_msg = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error_msg = "Error updating profile.";
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password !== $confirm_password) {
            $error_msg = "New passwords do not match.";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$volunteer_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $user['password'])) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$hash, $volunteer_id]);
                $success_msg = "Password changed successfully!";
            } else {
                $error_msg = "Current password is incorrect.";
            }
        }
    }
}

// Fetch user data
$user = [];
try {
    $stmt = $pdo->prepare("SELECT full_name, email, phone, bio, created_at FROM users WHERE id = ?");
    $stmt->execute([$volunteer_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<?php 
$page_title = "My Profile";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>My Profile</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Manage your personal information and security settings.</p>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div style="padding: 15px; background: rgba(16,185,129,0.1); color: var(--success); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div style="padding: 15px; background: rgba(239,68,68,0.1); color: var(--danger); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-sidebar">
                    <div class="glass-card" style="text-align: center;">
                        <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 20px;">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <h3 style="color: var(--text-dark); margin: 0 0 5px 0;"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></h3>
                        <p style="color: var(--text-muted); margin: 0 0 20px 0; font-size: 0.9rem;">Volunteer</p>
                        
                        <div style="text-align: left; padding-top: 20px; border-top: 1px solid rgba(0,0,0,0.05);">
                            <div style="margin-bottom: 10px;">
                                <strong style="color: var(--text-dark); font-size: 0.85rem;">Email:</strong><br>
                                <span style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--text-dark); font-size: 0.85rem;">Member Since:</strong><br>
                                <span style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-main">
                    <div class="glass-card" style="margin-bottom: 20px;">
                        <div class="card-header">
                            <h3 class="card-title">Personal Information</h3>
                        </div>
                        <form method="POST">
                            <div style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email Address (Cannot be changed)</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Bio</label>
                                <textarea name="bio" class="form-control" rows="4" placeholder="Tell us a bit about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="update_profile" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        </form>
                    </div>

                    <div class="glass-card">
                        <div class="card-header">
                            <h3 class="card-title">Security</h3>
                        </div>
                        <form method="POST">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="8">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn-primary" style="background: var(--danger);"><i class="fas fa-key"></i> Update Password</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

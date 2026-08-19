<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only NGO Admin (Role ID 2) can access
Middleware::role([2]);

$pdo = getDatabase();
$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $full_name = htmlspecialchars(trim($_POST['full_name']));
            $email = htmlspecialchars(trim($_POST['email']));
            $phone = htmlspecialchars(trim($_POST['phone']));
            
            if (empty($full_name) || empty($email)) {
                $error_msg = "Name and Email are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $phone, $user_id]);
                    $_SESSION['full_name'] = $full_name; // Update session
                    $_SESSION['email'] = $email;
                    $success_msg = "Profile updated successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Error updating profile. Email might be already in use.";
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                $error_msg = "New passwords do not match.";
            } elseif (strlen($new_password) < 8) {
                $error_msg = "New password must be at least 8 characters long.";
            } else {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $hash = $stmt->fetchColumn();
                
                if (Security::verifyPassword($current_password, $hash)) {
                    $new_hash = Security::hashPassword($new_password);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$new_hash, $user_id]);
                    $success_msg = "Password changed successfully.";
                } else {
                    $error_msg = "Incorrect current password.";
                }
            }
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<?php 
$page_title = "My Profile";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>My Profile</h1>
                    <div class="breadcrumb">
                        <span>Account</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Profile Settings</span>
                    </div>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert-message alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                
                <!-- Profile Settings Form -->
                <div class="glass-card" style="flex: 2; min-width: 400px;">
                    <div class="card-header">
                        <h3 class="card-title">Personal Information</h3>
                    </div>
                    <form method="POST" action="ngo_profile.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- Password Change Form -->
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Change Password</h3>
                    </div>
                    <form method="POST" action="ngo_profile.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn-primary" style="width: 100%;"><i class="fas fa-lock"></i> Update Password</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

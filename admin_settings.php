<?php
// admin_settings.php
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

// Check if settings row exists, if not, create it
$settingsCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
if ($settingsCount == 0) {
    $pdo->query("INSERT INTO settings (ngo_name) VALUES ('Arohan Foundation')");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Invalid security token.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $ngo_name = htmlspecialchars(trim($_POST['ngo_name']));
            $email = htmlspecialchars(trim($_POST['email']));
            $phone = htmlspecialchars(trim($_POST['phone']));
            $website = htmlspecialchars(trim($_POST['website']));
            $address = htmlspecialchars(trim($_POST['address']));
            $mission = htmlspecialchars(trim($_POST['mission']));
            $vision = htmlspecialchars(trim($_POST['vision']));
            $social_media_links = htmlspecialchars(trim($_POST['social_media_links'])); // Can be JSON string or simple text
            
            if ($ngo_name && $email) {
                try {
                    $stmt = $pdo->prepare("UPDATE settings SET 
                        ngo_name = ?, email = ?, phone = ?, website = ?, 
                        address = ?, mission = ?, vision = ?, social_media_links = ? 
                        WHERE id = 1"); // Assuming id 1 is the main settings row
                    
                    $stmt->execute([$ngo_name, $email, $phone, $website, $address, $mission, $vision, $social_media_links]);
                    $success_msg = "Organization profile updated successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                }
            } else {
                $error_msg = "Organization Name and Email are required.";
            }
        }
    }
}

// Fetch Current Settings
try {
    $settings = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
}

?>
<?php 
$page_title = "System Settings";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>System Settings</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Settings</span>
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

            <div class="settings-layout">
                <!-- Navigation -->
                <div class="settings-nav">
                    <a href="admin_settings.php" class="settings-nav-item active">
                        <i class="fas fa-building"></i> Organization Profile
                    </a>
                </div>

                <!-- Content -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h3>Organization Profile</h3>
                        <p>Manage the public details of your NGO shown across the platform.</p>
                    </div>
                    
                    <form method="POST" action="admin_settings.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="settings-body">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Organization Name *</label>
                                    <input class="form-control" type="text" name="ngo_name" value="<?php echo htmlspecialchars($settings['ngo_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Contact Email *</label>
                                    <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input class="form-control" type="text" name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Website URL</label>
                                    <input class="form-control" type="url" name="website" value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Office Address</label>
                                    <textarea class="form-control" name="address"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Mission Statement</label>
                                    <textarea class="form-control" name="mission"><?php echo htmlspecialchars($settings['mission'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Vision Statement</label>
                                    <textarea class="form-control" name="vision"><?php echo htmlspecialchars($settings['vision'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Social Media Links</label>
                                    <textarea class="form-control" name="social_media_links" placeholder="E.g., Facebook: https://..., Twitter: https://..."><?php echo htmlspecialchars($settings['social_media_links'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-footer">
                            <button type="submit" class="btn-primary" style="padding: 12px 25px;">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

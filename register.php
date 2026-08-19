<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/controllers/AuthController.php';

Middleware::guest(); // Only guests can see register

$auth = new AuthController();
$result = $auth->handleRegister();
$csrfToken = Security::generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo APP_NAME; ?></title>
    <!-- Core Design System -->
    <link rel="stylesheet" href="assets/css/landing.css">
    <!-- Premium Auth UI -->
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="auth-layout">
    
    <!-- LEFT SIDE: HERO -->
    <div class="auth-hero-wrapper">
        <div class="auth-hero"></div>
        <div class="auth-hero-overlay"></div>
        <div class="auth-noise"></div>
        <div class="auth-light"></div>

        <div class="particles">
            <div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
        </div>

        <div class="hero-content">
            <div class="hero-logo-container">
                <a href="index.php"><img src="assets/images/logo/arohan-logo.jpeg" alt="<?php echo APP_NAME; ?> Logo"></a>
                <div class="hero-brand-text">
                    <h2>Arohan Foundation</h2>
                    <span>Care • Heal • Empower • Uplift</span>
                </div>
            </div>
            
            <h1 class="hero-quote">
                Building a kinder society.<br>
                <span>Join us today.</span>
            </h1>
            
            <p class="hero-mission">
                Whether you want to support impactful campaigns as a donor or dedicate your time as a volunteer, your journey towards making a real difference begins here.
            </p>

            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-value">12,000+</span>
                    <span class="stat-label">Lives Changed</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">250+</span>
                    <span class="stat-label">Campaigns</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">150+</span>
                    <span class="stat-label">Volunteers</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">95%</span>
                    <span class="stat-label">Transparency</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: FORM -->
    <div class="auth-panel">
        <div class="auth-panel-deco"></div>
        <div class="auth-card">
            
            <div class="auth-header">
                <h2>Create Account</h2>
                <p>Join the movement as a donor or volunteer.</p>
            </div>

            <?php if (is_array($result) && isset($result['success'])): ?>
                <div class="alert-message alert-success">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($result['message'] ?? ''); ?>
                    <div style="margin-top: 15px; width: 100%;">
                        <a href="login.php" class="btn-auth" style="text-align: center; display: block; text-decoration: none; padding: 12px;">Proceed to Login</a>
                    </div>
                </div>
            <?php elseif (is_string($result)): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($result); ?>
                </div>
            <?php endif; ?>

            <?php if (!is_array($result)): ?>
            <form method="POST" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group" style="animation: fadeUpStagger 0.8s 2.1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; font-size: 0.9rem;">I want to become a</label>
                    <div style="position: relative;">
                        <select name="role" class="form-control" style="appearance: none; -webkit-appearance: none; background-color: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; font-size: 1rem; width: 100%; color: var(--text-dark); cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                            <option value="Donor">Donor</option>
                            <option value="Volunteer">Volunteer</option>
                        </select>
                        <i class="fas fa-chevron-down" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;"></i>
                    </div>
                </div>
                
                <div class="form-group" style="animation: fadeUpStagger 0.8s 2.2s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0;">
                    <i class="fas fa-user form-icon"></i>
                    <input type="text" name="full_name" class="form-control" required placeholder=" " autofocus>
                    <label class="floating-label">Full Name</label>
                </div>
                
                <div class="form-group" style="animation: fadeUpStagger 0.8s 2.3s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0;">
                    <i class="fas fa-envelope form-icon"></i>
                    <input type="email" name="email" class="form-control" required placeholder=" ">
                    <label class="floating-label">Email Address</label>
                </div>
                
                <div class="form-group" style="margin-bottom: 2.2rem; animation: fadeUpStagger 0.8s 2.4s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0;">
                    <i class="fas fa-lock form-icon"></i>
                    <input type="password" name="password" id="pwd-input" class="form-control" required placeholder=" ">
                    <label class="floating-label">Create Password</label>
                    <i class="fas fa-eye toggle-password" title="Toggle Password Visibility"></i>
                </div>
                
                <div class="pwd-strength-container" style="animation: fadeUpStagger 0.8s 2.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; margin-top:-20px;">
                    <div id="pwd-strength" class="pwd-strength">
                        <div id="pwd-bar" class="pwd-bar weak"></div>
                    </div>
                    <span id="pwd-text" class="pwd-text"></span>
                </div>

                <div class="form-group" style="animation: fadeUpStagger 0.8s 2.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0;">
                    <i class="fas fa-lock form-icon"></i>
                    <input type="password" name="confirm_password" class="form-control" required placeholder=" ">
                    <label class="floating-label">Confirm Password</label>
                    <i class="fas fa-eye toggle-password" title="Toggle Password Visibility"></i>
                </div>
                
                <div class="btn-wrapper">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>

</div>

<script src="assets/js/auth.js"></script>
</body>
</html>

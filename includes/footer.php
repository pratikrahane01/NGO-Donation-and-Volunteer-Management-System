<?php
/**
 * Reusable Footer Component
 * Part of Master Layout System
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
?>
<footer class="footer">
    <div class="footer-container">
        <!-- Logo & Quick Links Placeholder -->
        <div class="footer-brand">
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:var(--h4-size);font-weight:700;color:var(--text-primary);">
                <img src="assets/images/logo/arohan-logo.jpeg" alt="Arohan Foundation Logo" style="height: 120px; width: auto; object-fit: contain; border-radius: var(--radius-md); background: transparent;">
            </div>
            <p class="footer-desc">Building a kinder society. Together. Empowering communities through transparent donation and volunteer management.</p>
        </div>
        <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="javascript:void(0)">About Us</a></li>
                <li><a href="javascript:void(0)">Contact</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
            </ul>
        </div>
        <!-- Contact Placeholder -->
        <div class="footer-contact">
            <h4>Contact</h4>
            <p><i class="fas fa-envelope"></i> info@arohanfoundation.org</p>
            <p><i class="fas fa-phone"></i> +1 234 567 890</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> Arohan Foundation. All rights reserved.</p>
        <div class="footer-social">
            <!-- Social Placeholder -->
            <a href="https://twitter.com/arohan" class="social-icon"><i class="fab fa-twitter"></i></a>
            <a href="https://facebook.com/arohan" class="social-icon"><i class="fab fa-facebook"></i></a>
            <a href="https://instagram.com/arohan" class="social-icon"><i class="fab fa-instagram"></i></a>
        </div>
    </div>
</footer>

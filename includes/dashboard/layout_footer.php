<?php
// includes/dashboard/layout_footer.php
?>
        <!-- Shared Footer Component -->
        <footer class="dashboard-footer" style="margin-top: auto; padding: 20px; text-align: center; border-top: 1px solid rgba(0,0,0,0.05); color: var(--text-muted); font-size: 0.85rem; background: transparent;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong><?php echo htmlspecialchars(APP_NAME ?? 'Arohan Foundation'); ?></strong> &copy; <?php echo date('Y'); ?>. All Rights Reserved.
                </div>
                <div>
                    <a href="mailto:contact@arohan.org" style="color: inherit; text-decoration: none; margin-right: 15px;"><i class="fas fa-envelope"></i> contact@arohan.org</a>
                    <a href="<?php echo defined('APP_URL') ? APP_URL : '#'; ?>" style="color: inherit; text-decoration: none; margin-right: 15px;"><i class="fas fa-globe"></i> www.arohan.org</a>
                    <span>v1.0.0</span>
                </div>
            </div>
        </footer>
    </main>
</div>

<!-- Global AJAX Modal Container -->
<div id="globalModal" class="modal-backdrop">
    <div id="globalModalContent" style="display: contents;">
        <!-- Content will be injected here via AJAX -->
    </div>
</div>

<!-- Dashboard Interactive Logic -->
<script src="assets/js/app.js"></script>
<script src="assets/js/dashboard.js"></script>

<!-- Toastr JS for global notifications -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- Alert Display Script (Reads PHP session alerts) -->
<?php if(isset($_SESSION['success'])): ?>
<script>toastr.success("<?php echo addslashes($_SESSION['success']); ?>");</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<script>toastr.error("<?php echo addslashes($_SESSION['error']); ?>");</script>
<?php unset($_SESSION['error']); endif; ?>

</body>
</html>

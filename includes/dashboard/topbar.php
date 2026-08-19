<?php
// includes/dashboard/topbar.php
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="toggle-sidebar" id="toggle-sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <form action="admin_search.php" method="GET" class="search-form-container">
            <div class="search-bar">
                <input type="text" name="q" id="global-search-input" placeholder="Search campaigns, volunteers, donors...">
                <i class="fas fa-search search-icon"></i>
                <span class="shortcut-hint">/</span>
            </div>
        </form>
    </div>
    
    <div class="topbar-right">
        <div class="action-btn" style="position: relative;" data-toggle="dropdown" data-target="notification-dropdown">
            <i class="fas fa-bell"></i>
            <?php
            $notif_link = '#';
            if ($_SESSION['role_id'] == 1) $notif_link = 'admin_notifications.php';
            elseif ($_SESSION['role_id'] == 2) $notif_link = 'ngo_notifications.php';
            elseif ($_SESSION['role_id'] == 3) $notif_link = 'donor_notifications.php';
            elseif ($_SESSION['role_id'] == 4) $notif_link = 'volunteer_notifications.php';
            elseif ($_SESSION['role_id'] == 5) $notif_link = 'coordinator_notifications.php';
            ?>
            <?php
            $unreadCount = 0;
            $topbarNotifs = [];
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_id = ? AND role_id = ? AND read_status = 0 ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['role_id']]);
                $topbarNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND role_id = ? AND read_status = 0");
                $stmtCount->execute([$_SESSION['user_id'], $_SESSION['role_id']]);
                $unreadCount = $stmtCount->fetchColumn();
            }
            ?>
            <span class="badge"><?php echo $unreadCount; ?></span>
            
            <div class="dropdown-menu" id="notification-dropdown" style="right: -10px; width: 300px;">
                <div class="dropdown-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Notifications</span>
                    <a href="<?php echo $notif_link; ?>?action=mark_all_read" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">Mark all read</a>
                </div>
                <?php if (empty($topbarNotifs)): ?>
                    <div style="padding: 15px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($topbarNotifs as $notif): ?>
                        <a href="<?php echo $notif_link; ?>" class="dropdown-item">
                            <div style="width: 32px; height: 32px; background: rgba(59,130,246,0.1); color: var(--info); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div style="line-height: 1.3;">
                                <span style="font-size: 0.85rem; font-weight: 600; display: block; color: var(--text-dark);"><?php echo htmlspecialchars($notif['title'] ?? ''); ?></span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="padding: 10px; text-align: center; border-top: 1px solid rgba(0,0,0,0.05);">
                    <a href="<?php echo $notif_link; ?>" style="font-size: 0.8rem; font-weight: 600; color: var(--primary); text-decoration: none;">View All</a>
                </div>
            </div>
        </div>
        
        <?php if ($_SESSION['role_id'] == 1 || ($_SESSION['role_id'] ?? '') == 2): ?>
        <a href="<?php echo ($role_id == 2) ? 'ngo' : 'admin'; ?>_inquiries.php" class="action-btn" style="text-decoration: none; color: inherit;">
            <i class="fas fa-envelope"></i>
        </a>
        <?php endif; ?>
        
        <div class="action-btn user-avatar" style="border: none; background: var(--primary); color: white;" data-toggle="dropdown" data-target="user-dropdown-topbar">
            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
            <div class="dropdown-menu" id="user-dropdown-topbar" style="top: calc(100% + 15px);">
                <div class="dropdown-header">
                    <div style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
                </div>
                <?php if ($_SESSION['role_id'] == 1): ?>
                    <a href="admin_settings.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <?php elseif ($_SESSION['role_id'] == 2): ?>
                    <a href="ngo_profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <?php elseif ($_SESSION['role_id'] == 3): ?>
                    <a href="donor_profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <?php elseif ($_SESSION['role_id'] == 4): ?>
                    <a href="volunteer_profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <?php elseif ($_SESSION['role_id'] == 5): ?>
                    <a href="coordinator_profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <?php endif; ?>
                <a href="javascript:void(0)" class="dropdown-item"><i class="fas fa-cog"></i> Preferences</a>
                <div style="height: 1px; background: rgba(0,0,0,0.05); margin: 5px 0;"></div>
                <a href="logout.php" class="dropdown-item" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

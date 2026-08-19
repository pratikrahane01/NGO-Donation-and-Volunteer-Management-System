<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/donor_queries.php';

Middleware::role([3]);

$pdo = getDatabase();
$donor_id = $_SESSION['user_id'];
$campaigns = getDonorCampaigns($pdo, 100);

?>
<?php 
$page_title = "Active Campaigns";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="page-title">
                    <h1>Active Campaigns</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Discover and support causes that matter to you.</p>
                </div>
            </div>

            <?php if (empty($campaigns)): ?>
                <div class="glass-card" style="margin-top: 2rem;">
                    <?php render_empty_state('No Campaigns', 'No active campaigns available.', 'far fa-calendar-times'); ?>
                </div>
            <?php else: ?>
                <div class="campaign-grid">
                    <?php foreach ($campaigns as $camp): 
                        $percent = $camp['target_amount'] > 0 ? ($camp['collected_amount'] / $camp['target_amount']) * 100 : 0;
                        $percent = min(100, $percent);
                        $days_remaining = max(0, (strtotime($camp['end_date']) - time()) / (60 * 60 * 24));
                    ?>
                        <div class="campaign-card">
                            <div class="campaign-img">
                                <?php if (!empty($camp['campaign_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($camp['campaign_image'] ?? ''); ?>" alt="Campaign">
                                <?php else: ?>
                                    <i class="fas fa-image fa-3x" style="color: #cbd5e1;"></i>
                                <?php endif; ?>
                                <span class="campaign-cat"><i class="fas <?php echo htmlspecialchars($camp['category_icon'] ?? ''); ?>"></i> <?php echo htmlspecialchars($camp['category_name'] ?? ''); ?></span>
                            </div>
                            <div class="campaign-content">
                                <h3 class="campaign-title"><?php echo htmlspecialchars($camp['name'] ?? ''); ?></h3>
                                <p class="campaign-desc"><?php echo htmlspecialchars($camp['short_description'] ?? ''); ?></p>
                                
                                <div class="progress-wrapper">
                                    <div class="progress-stats">
                                        <span style="color: var(--primary);"><?php echo formatIndianCurrency($camp['collected_amount']); ?> raised</span>
                                        <span style="color: var(--text-muted);">Goal: <?php echo formatIndianCurrency($camp['target_amount']); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                </div>
                                
                                <div class="campaign-meta">
                                    <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($camp['organization_name'] ?? ''); ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo floor($days_remaining); ?> days left</span>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-top: auto;">
                                    <a href="donor_campaign_details.php?id=<?php echo $camp['id'] ?? ''; ?>" class="btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">Details</a>
                                    <a href="donor_donate.php?campaign_id=<?php echo $camp['id'] ?? ''; ?>" class="btn-primary" style="flex: 1; text-align: center; text-decoration: none;">Donate Now</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

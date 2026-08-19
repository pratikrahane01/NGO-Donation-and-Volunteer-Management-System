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
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$campaign = getCampaignDetails($pdo, $campaign_id);

if (!$campaign) {
    header("Location: donor_campaigns.php");
    exit;
}

// Fetch recent public donors for this campaign
$stmtDonors = $pdo->prepare("
    SELECT u.full_name, d.amount, d.donation_date 
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.campaign_id = ? AND d.payment_status = 'completed' AND d.is_anonymous = 0
    ORDER BY d.donation_date DESC 
    LIMIT 5
");
$stmtDonors->execute([$campaign_id]);
$recentDonors = $stmtDonors->fetchAll(PDO::FETCH_ASSOC);

$percent = $campaign['target_amount'] > 0 ? ($campaign['collected_amount'] / $campaign['target_amount']) * 100 : 0;
$percent = min(100, $percent);
$days_remaining = max(0, (strtotime($campaign['end_date']) - time()) / (60 * 60 * 24));
?>
<?php 
$page_title = "";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <a href="donor_campaigns.php" style="display: inline-block; margin-bottom: 15px; text-decoration: none; color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-arrow-left"></i> Back to Campaigns
            </a>

            <div class="detail-hero">
                <?php if (!empty($campaign['campaign_image'])): ?>
                    <img src="<?php echo htmlspecialchars($campaign['campaign_image'] ?? ''); ?>" alt="Campaign">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-image fa-5x" style="color: #cbd5e1;"></i>
                    </div>
                <?php endif; ?>
                <div class="detail-hero-overlay">
                    <div>
                        <div style="margin-bottom: 10px;">
                            <span style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                <i class="fas <?php echo htmlspecialchars($campaign['category_icon'] ?? ''); ?>"></i> <?php echo htmlspecialchars($campaign['category_name'] ?? ''); ?>
                            </span>
                            <?php if($campaign['status'] !== 'active'): ?>
                                <span style="background: var(--danger); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-left: 10px;">
                                    <?php echo ucfirst($campaign['status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h1 class="detail-title"><?php echo htmlspecialchars($campaign['name'] ?? ''); ?></h1>
                        <div class="detail-meta">
                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($campaign['organization_name'] ?? ''); ?></span>
                            <span><i class="far fa-calendar-alt"></i> Started <?php echo date('M d, Y', strtotime($campaign['start_date'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid-layout">
                <!-- Left Column: Description & Details -->
                <div>
                    <div class="glass-card" style="margin-bottom: 2rem;">
                        <h3 style="margin-top: 0; color: var(--text-dark); border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;">About this Campaign</h3>
                        <div style="line-height: 1.8; color: var(--text-body);">
                            <?php echo nl2br(htmlspecialchars($campaign['description'] ?? '')); ?>
                        </div>
                    </div>

                    <?php if (!empty($recentDonors)): ?>
                    <div class="glass-card">
                        <h3 style="margin-top: 0; color: var(--text-dark); border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;">Recent Supporters</h3>
                        <ul class="donor-list">
                            <?php foreach($recentDonors as $donor): ?>
                                <li class="donor-item">
                                    <div class="donor-info">
                                        <div class="donor-avatar">
                                            <?php echo strtoupper(substr($donor['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong style="color: var(--text-dark); display: block; font-size: 0.95rem;"><?php echo htmlspecialchars($donor['full_name'] ?? ''); ?></strong>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($donor['donation_date'])); ?></span>
                                        </div>
                                    </div>
                                    <strong style="color: var(--success);"><?php echo formatIndianCurrency($donor['amount']); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Donation Progress -->
                <div>
                    <div class="progress-box">
                        <h3 style="margin-top: 0; color: var(--text-dark); font-size: 1.5rem;"><?php echo formatIndianCurrency($campaign['collected_amount']); ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">raised of <?php echo formatIndianCurrency($campaign['target_amount']); ?></span></h3>
                        
                        <div class="progress-bar-lg">
                            <div class="progress-fill-lg" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        
                        <div class="stat-grid">
                            <div class="stat-item">
                                <span class="stat-val"><?php echo number_format($percent, 1); ?>%</span>
                                <span class="stat-label">Funded</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-val"><?php echo floor($days_remaining); ?></span>
                                <span class="stat-label">Days Left</span>
                            </div>
                        </div>

                        <?php if ($campaign['status'] === 'active'): ?>
                            <a href="donor_donate.php?campaign_id=<?php echo $campaign['id'] ?? ''; ?>" class="btn-primary" style="display: block; text-align: center; text-decoration: none; padding: 12px; font-size: 1.1rem;">Donate Now</a>
                        <?php else: ?>
                            <div style="background: #f8fafc; color: var(--text-muted); text-align: center; padding: 12px; border-radius: 8px; font-weight: 600;">
                                This campaign is no longer accepting donations.
                            </div>
                        <?php endif; ?>
                        
                        <div style="text-align: center; margin-top: 20px; font-size: 0.85rem; color: var(--text-muted);">
                            <i class="fas fa-shield-alt"></i> All payments are securely processed.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

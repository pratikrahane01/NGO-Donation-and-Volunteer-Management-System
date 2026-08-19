<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/donor_queries.php';

// Protect this dashboard: Only Donor (Role ID 3) can access
Middleware::role([3]);

$pdo = getDatabase();
$donor_id = $_SESSION['user_id'];

// Fetch Data utilizing unified data loader and cache
$dashboardData = getDonorDashboardData($pdo, $donor_id);
$kpis = $dashboardData['kpis'];
$activeCampaigns = $dashboardData['activeCampaigns'];
$recentActivity = $dashboardData['recentActivity'];
$notifications = $dashboardData['notifications'];
?>
<?php 
$page_title = "Donor Dashboard";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            
            <div class="page-header">
                <div class="page-title">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? 'Donor'))[0]); ?>! 👋</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Your contributions are making a real difference.</p>
                </div>
            </div>

            <!-- First Row: KPIs -->
            <div class="kpi-grid">
                <?php 
                render_kpi_card('Total Donations', $kpis['total_donations'], 'fas fa-hand-holding-heart', 'primary', 'donor_donations.php');
                render_kpi_card('Total Amount Donated', formatIndianCurrency($kpis['total_amount']), 'fas fa-rupee-sign', 'success', 'donor_donations.php');
                render_kpi_card('Campaigns Supported', $kpis['campaigns_supported'], 'fas fa-bullhorn', 'info', 'donor_campaigns.php');
                render_kpi_card('Average Donation', formatIndianCurrency($kpis['average_donation']), 'fas fa-chart-line', 'warning', 'donor_donations.php');
                render_kpi_card('Favorite Cause', $kpis['favorite_cause'], 'fas fa-star', 'primary', 'donor_campaigns.php');
                
                $last_don = $kpis['last_donation_date'] ? date('M d, Y', strtotime($kpis['last_donation_date'])) : 'N/A';
                render_kpi_card('Last Donation', $last_don, 'fas fa-calendar-check', 'info', 'donor_donations.php');
                ?>
            </div>

            <!-- Second Row: Activity and Campaigns -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 2rem;">
                
                <!-- Recent Activity Timeline -->
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <?php if (empty($recentActivity)): ?>
                        <?php render_empty_state('No Activity', 'No activity.', 'fas fa-history'); ?>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach($recentActivity as $activity): ?>
                                <div class="timeline-item" style="display: flex; gap: 15px; margin-bottom: 20px;">
                                    <div class="timeline-icon" style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $activity['color'] ?? ''; ?>20; color: <?php echo $activity['color'] ?? ''; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.2rem;">
                                        <i class="<?php echo $activity['icon'] ?? ''; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <strong style="color: var(--text-dark); display: block; font-size: 0.95rem;"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></strong>
                                        <span style="font-size: 0.85rem; color: var(--text-body);"><?php echo htmlspecialchars($activity['title'] ?? ''); ?></span>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><i class="far fa-clock"></i> <?php echo date('M d, g:i A', strtotime($activity['event_date'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Featured / Active Campaigns -->
                <div class="glass-card" style="flex: 2; min-width: 500px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">Active Campaigns</h3>
                        <a href="donor_campaigns.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 600;">View All</a>
                    </div>
                    <?php if (empty($activeCampaigns)): ?>
                        <?php render_empty_state('No Active Campaigns', 'No active campaigns available.', 'far fa-calendar-times'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Goal</th>
                                        <th>Progress</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $count = 0;
                                    foreach($activeCampaigns as $camp): 
                                        if ($count++ >= 4) break; // show top 4 on dash
                                        $percent = $camp['target_amount'] > 0 ? ($camp['collected_amount'] / $camp['target_amount']) * 100 : 0;
                                        $percent = min(100, $percent);
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="donor_campaign_details.php?id=<?php echo $camp['id'] ?? ''; ?>" style="text-decoration: none;">
                                                <strong style="color: var(--text-dark); display: block;"><?php echo htmlspecialchars($camp['name'] ?? ''); ?></strong>
                                            </a>
                                            <span style="font-size: 0.8rem; color: var(--text-muted);"><i class="fas <?php echo htmlspecialchars($camp['category_icon'] ?? ''); ?>"></i> <?php echo htmlspecialchars($camp['category_name'] ?? ''); ?></span>
                                        </td>
                                        <td><strong style="color: var(--primary);"><?php echo formatIndianCurrency($camp['target_amount']); ?></strong></td>
                                        <td>
                                            <div style="width: 100%; background: #e2e8f0; border-radius: 4px; height: 8px; margin-bottom: 4px;">
                                                <div style="width: <?php echo $percent; ?>%; background: var(--success); height: 100%; border-radius: 4px;"></div>
                                            </div>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo number_format($percent, 1); ?>% funded</span>
                                        </td>
                                        <td>
                                            <a href="donor_donate.php?campaign_id=<?php echo $camp['id'] ?? ''; ?>" class="btn-primary" style="padding: 5px 12px; font-size: 0.8rem; text-decoration: none;">Donate</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Third Row: Notifications -->
            <div style="margin-top: 2rem;">
                <div class="glass-card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">Unread Notifications</h3>
                        <a href="donor_notifications.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 600;">View All</a>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <?php render_empty_state('All caught up!', 'You\'re all caught up.', 'far fa-bell-slash'); ?>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach($notifications as $notif): ?>
                                <li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
                                    <a href="donor_notifications.php" style="text-decoration: none; color: inherit; display: flex; gap: 15px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(124,154,134,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div>
                                            <strong style="color: var(--text-dark); display: block; font-size: 0.95rem;"><?php echo htmlspecialchars($notif['title'] ?? ''); ?></strong>
                                            <p style="font-size: 0.85rem; color: var(--text-body); margin: 4px 0;"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></p>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></span>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

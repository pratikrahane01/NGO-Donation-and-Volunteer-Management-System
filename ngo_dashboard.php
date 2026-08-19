<?php
// admin_dashboard.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/ngo_queries.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only NGO Admin (Role ID 2) can access
Middleware::role([2]);

// Initialize Database
$pdo = getDatabase();

// Fetch Real Data
$kpis = get_dashboard_kpis($pdo);
$recentDonations = get_recent_donations($pdo, 10);
$upcomingEvents = get_upcoming_events($pdo, 5);
$latestCampaigns = get_latest_campaigns($pdo, 5);
$recentActivity = get_recent_activity($pdo, 8);
$chartData = get_chart_data($pdo);

// Encode chart data for JS
$chartLabelsJSON = json_encode($chartData['labels']);
$chartAmountsJSON = json_encode($chartData['amounts']);
?>
<?php 
$page_title = "NGO Admin Dashboard";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 5px;">Welcome Back,</p>
                    <h1>NGO Administrator</h1>
                    <div class="breadcrumb" style="margin-top: 5px;">
                        <span style="color: var(--primary);">Dashboard</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" onclick="window.location.href='ngo_campaigns.php'">
                        <i class="fas fa-plus"></i> New Campaign
                    </button>
                </div>
            </div>

            <!-- KPI Cards Section -->
            <div class="kpi-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatIndianCurrency($kpis['total_donations']); ?></h3>
                        <p>Total Raised</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['active_campaigns']); ?></h3>
                        <p>Active Campaigns</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: var(--info);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['total_volunteers']); ?></h3>
                        <p>Volunteers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(236, 72, 153, 0.1); color: var(--danger);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['total_events']); ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem;">
                <div class="glass-card" style="flex: 2; min-width: 500px;">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Donations</h3>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="donationsChart"></canvas>
                    </div>
                </div>
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div class="activity-feed" style="max-height: 300px; overflow-y: auto; padding-right: 10px;">
                        <?php if (empty($recentActivity)): ?>
                            <p style="text-align:center; color: var(--text-muted); margin-top: 2rem;">No recent activity.</p>
                        <?php else: ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach($recentActivity as $log): ?>
                                    <li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">
                                            <i class="far fa-clock"></i> <?php echo date('M d, g:i A', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 0.95rem; color: var(--text-dark);">
                                            <strong><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></strong>: 
                                            <?php echo htmlspecialchars(ucfirst($log['module']) . ' - ' . ucfirst($log['action'])); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Complex Data Tables Row -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem;">
                
                <!-- Recent Donations -->
                <div class="glass-card" style="flex: 2; min-width: 600px;">
                    <div class="card-header">
                        <h3 class="card-title">Recent Donations</h3>
                        <button class="btn-primary" style="padding: 6px 15px; font-size: 0.8rem; background: rgba(124,154,134,0.1); color: var(--primary);">View All</button>
                    </div>
                    
                    <?php if (empty($recentDonations)): ?>
                        <?php render_empty_state('No Donations Yet', 'There are no completed donations in the system.', 'fas fa-receipt'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Donor</th>
                                        <th>Campaign</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentDonations as $donation): ?>
                                    <tr>
                                        <td><strong style="color: var(--text-dark);"><?php echo htmlspecialchars($donation['donor_name'] ?? 'Anonymous'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($donation['campaign_name'] ?? 'General Fund'); ?></td>
                                        <td style="font-family: var(--font-stats); font-weight: 700; color: var(--primary);"><?php echo formatIndianCurrency($donation['amount']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'status-inactive';
                                            if ($donation['payment_status'] == 'completed') $statusClass = 'status-active';
                                            if ($donation['payment_status'] == 'pending') $statusClass = 'status-pending';
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($donation['payment_status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Empty spot where recent users used to be, to keep flex layout balanced we just take full width for donations or leave it -->
            </div>

            <!-- Third Row: Campaigns and Events -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                <!-- Latest Campaigns -->
                <div class="glass-card" style="flex: 1; min-width: 500px;">
                    <div class="card-header">
                        <h3 class="card-title">Latest Campaigns</h3>
                    </div>
                    <?php if (empty($latestCampaigns)): ?>
                        <?php render_empty_state('No Campaigns', 'Create your first campaign to start fundraising.', 'fas fa-bullhorn', 'Create Campaign'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($latestCampaigns as $camp): ?>
                                    <tr>
                                        <td><strong style="color: var(--text-dark);"><?php echo htmlspecialchars($camp['name'] ?? ''); ?></strong></td>
                                        <td style="width: 40%;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 5px; font-weight: 600;">
                                                <span style="color: var(--primary);"><?php echo formatIndianCurrency($camp['collected_amount']); ?></span>
                                                <span style="color: var(--text-muted);"><?php echo formatIndianCurrency($camp['target_amount']); ?></span>
                                            </div>
                                            <div style="height: 6px; width: 100%; background: rgba(0,0,0,0.05); border-radius: 3px; overflow: hidden;">
                                                <div style="height: 100%; background: var(--primary); width: <?php echo htmlspecialchars($camp['goal_completed_percentage'] ?? ''); ?>%;"></div>
                                            </div>
                                        </td>
                                        <td><span class="status-badge <?php echo ($camp['status'] ?? '') == 'active' ? 'status-active' : 'status-pending'; ?>"><?php echo ucfirst($camp['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Events -->
                <div class="glass-card" style="flex: 1; min-width: 400px;">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Events</h3>
                    </div>
                    <?php if (empty($upcomingEvents)): ?>
                        <?php render_empty_state('No Events', 'There are no upcoming events scheduled.', 'far fa-calendar-times'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Date & Time</th>
                                        <th>Venue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($upcomingEvents as $evt): ?>
                                    <tr>
                                        <td><strong style="color: var(--text-dark);"><?php echo htmlspecialchars($evt['title'] ?? ''); ?></strong></td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--text-dark); font-weight: 600;"><?php echo date('M d, Y', strtotime($evt['event_date'])); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('g:i A', strtotime($evt['event_time'])); ?></div>
                                        </td>
                                        <td><span style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['venue'] ?? ''); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fourth Row: Communications -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 2rem;">
                
                <!-- Unread Notifications -->
                <div class="glass-card" style="flex: 1; min-width: 400px;">
                    <div class="card-header">
                        <h3 class="card-title">Unread Notifications</h3>
                    </div>
                    <?php 
                    $notifications = get_unread_notifications($pdo, 5);
                    if (empty($notifications)): 
                        render_empty_state('All caught up!', 'You have no unread notifications.', 'far fa-bell-slash');
                    else: 
                    ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach($notifications as $notif): ?>
                                <li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
                                    <div style="display: flex; gap: 15px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(124,154,134,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div>
                                            <strong style="color: var(--text-dark); display: block; font-size: 0.95rem;"><?php echo htmlspecialchars($notif['title'] ?? ''); ?></strong>
                                            <p style="font-size: 0.85rem; color: var(--text-body); margin: 4px 0;"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></p>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Contact Requests -->
                <div class="glass-card" style="flex: 1; min-width: 400px;">
                    <div class="card-header">
                        <h3 class="card-title">Latest Contact Requests</h3>
                    </div>
                    <?php 
                    $messages = get_contact_messages($pdo, 5);
                    if (empty($messages)): 
                        render_empty_state('Inbox Empty', 'No pending contact requests at this time.', 'far fa-envelope');
                    else: 
                    ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($messages as $msg): ?>
                                    <tr>
                                        <td>
                                            <a href="ngo_inquiry_detail.php?id=<?php echo $msg['id'] ?? ''; ?>" style="text-decoration: none;">
                                                <strong style="color: var(--text-dark); display:block;"><?php echo htmlspecialchars($msg['name'] ?? ''); ?></strong>
                                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($msg['email'] ?? ''); ?></span>
                                            </a>
                                        </td>
                                        <td style="font-size: 0.9rem;"><?php echo htmlspecialchars($msg['subject'] ?? ''); ?></td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('donationsChart');
    if (ctx) {
        const labels = <?php echo $chartLabelsJSON; ?>;
        const amounts = <?php echo $chartAmountsJSON; ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Donations (₹)',
                    data: amounts,
                    borderColor: '#7C9A86',
                    backgroundColor: 'rgba(124, 154, 134, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#7C9A86',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1F2937',
                        bodyColor: '#4B5563',
                        borderColor: 'rgba(0,0,0,0.05)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '₹' + context.parsed.y.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "'Manrope', sans-serif" }, color: '#9CA3AF' }
                    },
                    y: {
                        grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                        ticks: { 
                            font: { family: "'Space Grotesk', sans-serif" }, 
                            color: '#9CA3AF',
                            callback: function(value) { return '₹' + value; }
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }
});
</script>
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

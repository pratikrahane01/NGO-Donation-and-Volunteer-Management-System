<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/analytics_queries.php';

// Only Super Admin
Middleware::role([1]);

$pdo = getDatabase();
$data = getAnalyticsDashboardData($pdo);

// Extract segments
$sys = $data['system'];
$don = $data['donations'];
$camp = $data['campaigns'];
$vol = $data['volunteers'];
$evt = $data['events'];
$fund = $data['fundraising'];
$activity = $data['recent_activity'];

// Helper to convert arrays to JS for charts
function toJson($arr) { return json_encode($arr); }
?>
<?php 
$page_title = "Central Analytics";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <div class="page-header">
                <div class="page-title">
                    <h1>Central Analytics Dashboard</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Executive control center for the NGO ecosystem.</p>
                </div>
            </div>

            <!-- SECTION 1: SYSTEM OVERVIEW -->
            <div class="analytics-section">
                <h2 class="section-title">System Overview</h2>
                <div class="kpi-grid">
                    <?php 
                    render_kpi_card('Total Users', $sys['total_users'], 'fas fa-users', 'primary', 'admin_users.php');
                    render_kpi_card('Active Campaigns', $sys['active_campaigns'], 'fas fa-bullhorn', 'success', '#');
                    render_kpi_card('Total Donations', $sys['total_donations'], 'fas fa-hand-holding-heart', 'info', '#');
                    render_kpi_card('Total Raised', formatIndianCurrency($sys['total_donation_amount']), 'fas fa-dollar-sign', 'warning', '#');
                    render_kpi_card('Total Volunteers', $sys['total_volunteers'], 'fas fa-hands-helping', 'primary', '#');
                    render_kpi_card('Events Conducted', $sys['completed_events'], 'fas fa-calendar-check', 'success', '#');
                    ?>
                </div>
            </div>

            <!-- SECTION 2: DONATION ANALYTICS -->
            <div class="analytics-section">
                <h2 class="section-title">Donation Analytics</h2>
                <div class="kpi-grid" style="margin-bottom: 20px;">
                    <div class="glass-card" style="text-align:center;">
                        <h4 style="margin:0; color:var(--text-muted);">Today</h4>
                        <strong style="font-size:1.5rem; color:var(--text-dark);"><?php echo formatIndianCurrency($don['metrics']['today'] ?? 0); ?></strong>
                    </div>
                    <div class="glass-card" style="text-align:center;">
                        <h4 style="margin:0; color:var(--text-muted);">This Week</h4>
                        <strong style="font-size:1.5rem; color:var(--text-dark);"><?php echo formatIndianCurrency($don['metrics']['this_week'] ?? 0); ?></strong>
                    </div>
                    <div class="glass-card" style="text-align:center;">
                        <h4 style="margin:0; color:var(--text-muted);">This Month</h4>
                        <strong style="font-size:1.5rem; color:var(--text-dark);"><?php echo formatIndianCurrency($don['metrics']['this_month'] ?? 0); ?></strong>
                    </div>
                    <div class="glass-card" style="text-align:center;">
                        <h4 style="margin:0; color:var(--text-muted);">Avg Donation</h4>
                        <strong style="font-size:1.5rem; color:var(--primary);"><?php echo formatIndianCurrency($don['metrics']['average_donation'] ?? 0); ?></strong>
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="chart-container">
                        <canvas id="monthlyDonationsChart"></canvas>
                    </div>
                    <div class="chart-container pie">
                        <canvas id="donationStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: CAMPAIGN PERFORMANCE -->
            <div class="analytics-section">
                <h2 class="section-title">Campaign Performance</h2>
                <div class="grid-2">
                    <div class="chart-container">
                        <canvas id="topCampaignsChart"></canvas>
                    </div>
                    <div class="glass-card" style="overflow-y: auto; max-height: 350px;">
                        <h3 style="margin-top:0;">Top Campaigns Progress</h3>
                        <?php foreach($camp['top_campaigns'] as $c): ?>
                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px;">
                                    <strong><?php echo htmlspecialchars($c['name'] ?? ''); ?></strong>
                                    <span style="color:var(--text-muted);"><?php echo formatIndianCurrency($c['collected_amount']); ?> / <?php echo formatIndianCurrency($c['target_amount']); ?></span>
                                </div>
                                <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo min(100, $c['percent']); ?>%; background: var(--primary);"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: VOLUNTEER & EVENT ANALYTICS -->
            <div class="analytics-section">
                <h2 class="section-title">Community Analytics (Volunteers & Events)</h2>
                <div class="grid-3">
                    <div class="glass-card" style="text-align:center;">
                        <h4 style="margin:0 0 10px 0; color:var(--text-muted);">Volunteer Attendance Rate</h4>
                        <div style="position: relative; width: 150px; height: 150px; margin: 0 auto;">
                            <canvas id="attendanceChart"></canvas>
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:1.5rem; font-weight:700; color:var(--text-dark);">
                                <?php echo number_format($vol['attendance_rate'], 1); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="glass-card" style="display:flex; flex-direction:column; justify-content:center; text-align:center;">
                        <h4 style="margin:0; color:var(--text-muted);">Tasks Completed</h4>
                        <strong style="font-size:3rem; color:var(--success);"><?php echo $vol['tasks']['completed_tasks'] ?? 0; ?></strong>
                        <span style="color:var(--text-muted); font-size:0.85rem;">of <?php echo $vol['tasks']['total_tasks'] ?? 0; ?> total tasks</span>
                    </div>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="monthlyEventsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- SECTION 6: FUNDRAISING ANALYSIS -->
            <div class="analytics-section">
                <h2 class="section-title">Fundraising Analysis</h2>
                <div class="grid-2">
                    <div class="chart-container">
                        <canvas id="categoryPerformanceChart"></canvas>
                    </div>
                    <div class="glass-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
                        <h3 style="margin:0 0 10px 0; color:var(--text-muted);">Goal vs Raised</h3>
                        <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-dark);">
                            <?php echo formatIndianCurrency($fund['goal_vs_raised']['goal'] ?? 0); ?>
                        </div>
                        <span style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;">System-wide Campaign Goal</span>
                        
                        <div style="width: 100%; height: 15px; background: #e2e8f0; border-radius: 8px; margin-bottom: 10px; overflow: hidden;">
                            <?php 
                            $goal = $fund['goal_vs_raised']['goal'] ?: 1;
                            $pct = ($fund['goal_vs_raised']['raised'] / $goal) * 100;
                            ?>
                            <div style="width: <?php echo min(100, $pct); ?>%; height: 100%; background: var(--success);"></div>
                        </div>
                        <strong style="color: var(--success); font-size: 1.5rem;"><?php echo formatIndianCurrency($fund['goal_vs_raised']['raised'] ?? 0); ?> Raised</strong>
                    </div>
                </div>
            </div>

            <!-- SECTION 7: REPORTS -->
            <div class="analytics-section">
                <h2 class="section-title">Data Exports & Reports</h2>
                <div class="grid-3">
                    <div class="report-card">
                        <i class="fas fa-file-invoice-dollar report-icon"></i>
                        <h3>Financial Report</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">Complete ledger of all donations, receipts, and campaign disbursements.</p>
                        <div class="export-btns">
                            <a href="javascript:void(0)" onclick="alert('PDF Export Generation Initiated (Placeholder)')">PDF</a>
                            <a href="javascript:void(0)" onclick="alert('CSV Export Generation Initiated (Placeholder)')">CSV</a>
                        </div>
                    </div>
                    <div class="report-card">
                        <i class="fas fa-users report-icon"></i>
                        <h3>Volunteer Report</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">Attendance records, hours contributed, and task completion rates.</p>
                        <div class="export-btns">
                            <a href="javascript:void(0)" onclick="alert('PDF Export Generation Initiated (Placeholder)')">PDF</a>
                            <a href="javascript:void(0)" onclick="alert('Excel Export Generation Initiated (Placeholder)')">Excel</a>
                        </div>
                    </div>
                    <div class="report-card">
                        <i class="fas fa-calendar-alt report-icon"></i>
                        <h3>Event Report</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">Past and upcoming events, coordinators, and expected budgets.</p>
                        <div class="export-btns">
                            <a href="javascript:void(0)" onclick="alert('PDF Export Generation Initiated (Placeholder)')">PDF</a>
                            <a href="javascript:void(0)" onclick="alert('CSV Export Generation Initiated (Placeholder)')">CSV</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 8: RECENT ACTIVITY -->
            <div class="analytics-section">
                <h2 class="section-title">System Unified Activity Stream</h2>
                <div class="glass-card">
                    <?php if (empty($activity)): ?>
                        <?php render_empty_state('No Activity', 'System is idle.', 'fas fa-history'); ?>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach($activity as $act): ?>
                                <div class="timeline-item" style="display: flex; gap: 15px; margin-bottom: 15px;">
                                    <div class="timeline-icon" style="width: 35px; height: 35px; border-radius: 50%; background: <?php echo $act['color'] ?? ''; ?>20; color: <?php echo $act['color'] ?? ''; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="<?php echo $act['icon'] ?? ''; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <strong style="color: var(--text-dark); display: block; font-size: 0.9rem;"><?php echo htmlspecialchars($act['title'] ?? ''); ?></strong>
                                        <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($act['event_date'])); ?> &bull; <?php echo htmlspecialchars($act['module'] ?? ''); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <script>
    // Chart Defaults
    Chart.defaults.font.family = "'Manrope', sans-serif";
    Chart.defaults.color = "#64748b";

    // 1. Monthly Donations Line Chart
    const monthlyDonData = <?php echo toJson($don['monthly_trend'] ?? []); ?>;
    if(document.getElementById('monthlyDonationsChart')) {
        new Chart(document.getElementById('monthlyDonationsChart'), {
            type: 'line',
            data: {
                labels: monthlyDonData.map(d => d.month_label),
                datasets: [{
                    label: 'Donations (₹)',
                    data: monthlyDonData.map(d => d.total),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Monthly Donations Trend' } } }
        });
    }

    // 2. Donation Status Pie Chart
    const statusData = <?php echo toJson($don['status_dist'] ?? []); ?>;
    if(document.getElementById('donationStatusChart')) {
        new Chart(document.getElementById('donationStatusChart'), {
            type: 'pie',
            data: {
                labels: statusData.map(d => d.payment_status),
                datasets: [{
                    data: statusData.map(d => d.count),
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#64748b']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' }, title: { display: true, text: 'Donation Status Distribution' } } }
        });
    }

    // 3. Top Campaigns Bar Chart
    const topCampData = <?php echo toJson($camp['top_campaigns'] ?? []); ?>;
    if(document.getElementById('topCampaignsChart')) {
        new Chart(document.getElementById('topCampaignsChart'), {
            type: 'bar',
            data: {
                labels: topCampData.map(d => d.name.length > 15 ? d.name.substring(0,15)+'...' : d.name),
                datasets: [{
                    label: 'Raised Amount (₹)',
                    data: topCampData.map(d => d.collected_amount),
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Top 10 Campaigns' } } }
        });
    }

    // 4. Volunteer Attendance Doughnut
    const attData = <?php echo toJson($vol['status_dist'] ?? []); ?>;
    if(document.getElementById('attendanceChart')) {
        new Chart(document.getElementById('attendanceChart'), {
            type: 'doughnut',
            data: {
                labels: attData.map(d => d.status),
                datasets: [{
                    data: attData.map(d => d.count),
                    backgroundColor: ['#3b82f6', '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false }, tooltip: { enabled: true } } }
        });
    }

    // 5. Monthly Events Area Chart
    const evtData = <?php echo toJson($evt['monthly_trend'] ?? []); ?>;
    if(document.getElementById('monthlyEventsChart')) {
        new Chart(document.getElementById('monthlyEventsChart'), {
            type: 'bar',
            data: {
                labels: evtData.map(d => d.month_label),
                datasets: [{
                    label: 'Events Conducted',
                    data: evtData.map(d => d.count),
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Monthly Event Frequency' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    // 6. Category Performance Horizontal Bar
    const catData = <?php echo toJson($fund['category_performance'] ?? []); ?>;
    if(document.getElementById('categoryPerformanceChart')) {
        new Chart(document.getElementById('categoryPerformanceChart'), {
            type: 'bar',
            data: {
                labels: catData.map(d => d.name),
                datasets: [{
                    label: 'Total Raised (₹)',
                    data: catData.map(d => d.raised),
                    backgroundColor: '#8b5cf6',
                    borderRadius: 4
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Fundraising by Category' } } }
        });
    }
</script>
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

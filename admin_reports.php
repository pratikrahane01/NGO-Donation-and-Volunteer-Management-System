<?php
// admin_reports.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Super Admin (Role ID 1) can access
Middleware::role([1]);

$pdo = getDatabase();

// Default date range (last 6 months)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // 1. Financial Analytics (Donations over time)
    $stmt1 = $pdo->prepare("
        SELECT DATE_FORMAT(donation_date, '%Y-%m') as month, SUM(amount) as total 
        FROM donations 
        WHERE payment_status = 'completed' AND donation_date BETWEEN ? AND ? 
        GROUP BY month 
        ORDER BY month ASC
    ");
    $stmt1->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $monthlyDonations = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // 2. Campaign Performance
    $stmt2 = $pdo->prepare("
        SELECT c.name, SUM(d.amount) as total_raised, COUNT(d.id) as donation_count 
        FROM campaigns c 
        LEFT JOIN donations d ON c.id = d.campaign_id 
        WHERE d.payment_status = 'completed' AND d.donation_date BETWEEN ? AND ? 
        GROUP BY c.id 
        ORDER BY total_raised DESC 
        LIMIT 5
    ");
    $stmt2->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $topCampaigns = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // 3. Volunteer Impact (Hours)
    $stmt3 = $pdo->prepare("
        SELECT u.full_name, SUM(TIMESTAMPDIFF(MINUTE, a.check_in, a.check_out)/60) as total_hours 
        FROM attendance a 
        JOIN users u ON a.volunteer_id = u.id 
        WHERE a.attendance_status = 'present' AND a.check_in BETWEEN ? AND ? 
        GROUP BY u.id 
        ORDER BY total_hours DESC 
        LIMIT 5
    ");
    $stmt3->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $topVolunteers = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // Summary Totals for Date Range
    $stmtSummary = $pdo->prepare("
        SELECT 
            (SELECT SUM(amount) FROM donations WHERE payment_status = 'completed' AND donation_date BETWEEN ?) as total_donations,
            (SELECT COUNT(*) FROM donations WHERE payment_status = 'completed' AND donation_date BETWEEN ?) as total_transactions,
            (SELECT COUNT(DISTINCT volunteer_id) FROM attendance WHERE attendance_status = 'present' AND check_in BETWEEN ?) as active_volunteers
    ");
    // PDO doesn't allow same parameter binding multiple times with positional ? easily in all drivers, so let's just do individual queries for safety.

} catch (PDOException $e) {
    $monthlyDonations = [];
    $topCampaigns = [];
    $topVolunteers = [];
}

// Safer summary queries
try {
    $sum1 = $pdo->prepare("SELECT SUM(amount) FROM donations WHERE payment_status = 'completed' AND donation_date BETWEEN ? AND ?");
    $sum1->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_donations = $sum1->fetchColumn() ?: 0;
    
    $sum2 = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE payment_status = 'completed' AND donation_date BETWEEN ? AND ?");
    $sum2->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_transactions = $sum2->fetchColumn() ?: 0;
    
    $sum3 = $pdo->prepare("SELECT COUNT(DISTINCT volunteer_id) FROM attendance WHERE attendance_status = 'present' AND check_in BETWEEN ? AND ?");
    $sum3->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $active_volunteers = $sum3->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $total_donations = 0; $total_transactions = 0; $active_volunteers = 0;
}

// Chart Data Prep
$chartLabels = [];
$chartData = [];
foreach ($monthlyDonations as $md) {
    $chartLabels[] = date('M Y', strtotime($md['month'] . '-01'));
    $chartData[] = $md['total'];
}

?>
<?php 
$page_title = "Reports & Analytics";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Reports & Analytics</h1>
                    <div class="breadcrumb">
                        <span>Dashboard</span>
                        <span style="margin: 0 8px;">/</span>
                        <span style="color: var(--primary);">Reports</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" style="background: white; color: var(--text-dark); border: 1px solid rgba(0,0,0,0.1);" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Filter -->
            <form method="GET" action="admin_reports.php" class="filter-bar print-hide">
                <div class="filter-group">
                    <label>From Date</label>
                    <input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input class="form-control" type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-sync-alt"></i> Update Report</button>
            </form>

            <!-- Summary Totals -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Total Donations</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-dark);"><?php echo formatIndianCurrency($total_donations); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Transactions</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-dark);"><?php echo number_format($total_transactions); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(124, 154, 134, 0.1); color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Active Volunteers</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-dark);"><?php echo number_format($active_volunteers); ?></div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="chart-container">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--text-dark);">Donation Trends</h3>
                <canvas id="donationChart" height="80"></canvas>
            </div>

            <!-- Tables -->
            <div class="tables-grid">
                <!-- Top Campaigns -->
                <div class="data-card">
                    <div class="data-header">Top Performing Campaigns</div>
                    <ul class="data-list">
                        <?php if(empty($topCampaigns)): ?>
                            <li style="color: var(--text-muted);">No data available in this date range.</li>
                        <?php else: ?>
                            <?php foreach($topCampaigns as $tc): ?>
                                <li>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($tc['name'] ?? ''); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $tc['donation_count'] ?? ''; ?> donations</div>
                                    </div>
                                    <div style="font-weight: 700; color: var(--success);">
                                        <?php echo formatIndianCurrency($tc['total_raised']); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Top Volunteers -->
                <div class="data-card">
                    <div class="data-header">Top Volunteers by Hours</div>
                    <ul class="data-list">
                        <?php if(empty($topVolunteers)): ?>
                            <li style="color: var(--text-muted);">No data available in this date range.</li>
                        <?php else: ?>
                            <?php foreach($topVolunteers as $tv): ?>
                                <li>
                                    <div style="font-weight: 600; color: var(--text-dark);">
                                        <?php echo htmlspecialchars($tv['full_name'] ?? ''); ?>
                                    </div>
                                    <div style="font-weight: 700; color: var(--primary);">
                                        <?php echo round($tv['total_hours'], 1); ?> Hrs
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
        </div>
    <script>
    // Initialize Chart
    const ctx = document.getElementById('donationChart').getContext('2d');
    
    // Create gradient
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(124, 154, 134, 0.5)'); // var(--primary)
    gradient.addColorStop(1, 'rgba(124, 154, 134, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Donations (₹)',
                data: <?php echo json_encode($chartData); ?>,
                borderColor: '#7c9a86', // var(--primary)
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#7c9a86',
                pointBorderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return '₹' + value;
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

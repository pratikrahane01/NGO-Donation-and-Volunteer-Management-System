<?php
/**
 * Shared queries for the Central Analytics Dashboard (Super Admin, Role ID = 1)
 * Consolidates all ecosystem data with request-level caching.
 */

$_ANALYTICS_CACHE = [];

function getAnalyticsDashboardData(PDO $pdo) {
    global $_ANALYTICS_CACHE;
    $cacheKey = "global_analytics";
    
    if (isset($_ANALYTICS_CACHE[$cacheKey])) {
        return $_ANALYTICS_CACHE[$cacheKey];
    }

    $data = [
        'system' => getSystemKPIs($pdo),
        'donations' => getDonationAnalytics($pdo),
        'campaigns' => getCampaignAnalytics($pdo),
        'volunteers' => getVolunteerAnalytics($pdo),
        'events' => getEventAnalytics($pdo),
        'fundraising' => getFundraisingAnalytics($pdo),
        'recent_activity' => getSystemRecentActivity($pdo, 15)
    ];

    $_ANALYTICS_CACHE[$cacheKey] = $data;
    return $data;
}

function getSystemKPIs(PDO $pdo) {
    // Fast conditional aggregations across main tables
    $kpis = [];

    // Users & Roles
    $stmtUsers = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as total_ngos,
            SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) as total_donors,
            SUM(CASE WHEN role_id = 4 THEN 1 ELSE 0 END) as total_volunteers,
            SUM(CASE WHEN role_id = 5 THEN 1 ELSE 0 END) as total_coordinators
        FROM users WHERE status != 'banned'
    ");
    $userStats = $stmtUsers->fetch(PDO::FETCH_ASSOC);

    // Campaigns
    $stmtCamps = $pdo->query("
        SELECT 
            COUNT(*) as total_campaigns,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_campaigns,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_campaigns,
            SUM(target_amount) as all_time_target,
            SUM(collected_amount) as all_time_raised
        FROM campaigns
    ");
    $campStats = $stmtCamps->fetch(PDO::FETCH_ASSOC);

    // Events
    $stmtEvents = $pdo->query("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events
        FROM events
    ");
    $eventStats = $stmtEvents->fetch(PDO::FETCH_ASSOC);

    // Merge
    $kpis = array_merge($userStats, $campStats, $eventStats);
    
    // Total Donations
    $stmtDon = $pdo->query("SELECT COUNT(*) as total_donations, SUM(amount) as total_donation_amount FROM donations WHERE payment_status = 'completed'");
    $kpis = array_merge($kpis, $stmtDon->fetch(PDO::FETCH_ASSOC));

    return $kpis;
}

function getDonationAnalytics(PDO $pdo) {
    $analytics = [];
    
    // Time-based metrics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN DATE(donation_date) = CURDATE() THEN amount ELSE 0 END) as today,
            SUM(CASE WHEN YEARWEEK(donation_date, 1) = YEARWEEK(CURDATE(), 1) THEN amount ELSE 0 END) as this_week,
            SUM(CASE WHEN MONTH(donation_date) = MONTH(CURDATE()) AND YEAR(donation_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as this_month,
            SUM(CASE WHEN YEAR(donation_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as this_year,
            AVG(amount) as average_donation,
            MAX(amount) as highest_donation
        FROM donations WHERE payment_status = 'completed'
    ");
    $analytics['metrics'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtReceipts = $pdo->query("SELECT COUNT(*) as generated FROM donation_receipts");
    $analytics['metrics']['receipts'] = $stmtReceipts->fetchColumn();

    // Line Chart: Monthly Donations (Last 6 Months)
    $stmtTrend = $pdo->query("
        SELECT DATE_FORMAT(donation_date, '%b %Y') as month_label, SUM(amount) as total
        FROM donations
        WHERE payment_status = 'completed' AND donation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(donation_date), MONTH(donation_date)
        ORDER BY YEAR(donation_date) ASC, MONTH(donation_date) ASC
    ");
    $analytics['monthly_trend'] = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    // Pie Chart: Donation Status
    $stmtStatus = $pdo->query("
        SELECT payment_status, COUNT(*) as count 
        FROM donations 
        GROUP BY payment_status
    ");
    $analytics['status_dist'] = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

    return $analytics;
}

function getCampaignAnalytics(PDO $pdo) {
    $analytics = [];

    // Top 10 Campaigns by raised
    $stmtTop = $pdo->query("
        SELECT name, collected_amount, target_amount, (collected_amount/target_amount)*100 as percent
        FROM campaigns
        WHERE target_amount > 0
        ORDER BY collected_amount DESC
        LIMIT 10
    ");
    $analytics['top_campaigns'] = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    return $analytics;
}

function getVolunteerAnalytics(PDO $pdo) {
    $analytics = [];

    $stmtAtt = $pdo->query("
        SELECT 
            COUNT(id) as total_attendance,
            SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) as present_count
        FROM attendance
    ");
    $att = $stmtAtt->fetch(PDO::FETCH_ASSOC);
    $analytics['attendance_rate'] = $att['total_attendance'] > 0 ? ($att['present_count'] / $att['total_attendance']) * 100 : 0;

    $stmtTasks = $pdo->query("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM tasks
    ");
    $analytics['tasks'] = $stmtTasks->fetch(PDO::FETCH_ASSOC);

    // Mock Volunteer Hours (since schema doesn't have an hours field natively yet, we derive from checked-in time or mock it based on present attendance * 4 hours avg)
    $analytics['estimated_hours'] = $att['present_count'] * 4; 
    
    // Status Dist
    $analytics['status_dist'] = [
        ['status' => 'Present', 'count' => $att['present_count']],
        ['status' => 'Absent/Late', 'count' => $att['total_attendance'] - $att['present_count']]
    ];

    return $analytics;
}

function getEventAnalytics(PDO $pdo) {
    $analytics = [];

    // Monthly Events
    $stmtTrend = $pdo->query("
        SELECT DATE_FORMAT(event_date, '%b %Y') as month_label, COUNT(*) as count
        FROM events
        WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(event_date), MONTH(event_date)
        ORDER BY YEAR(event_date) ASC, MONTH(event_date) ASC
    ");
    $analytics['monthly_trend'] = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    return $analytics;
}

function getFundraisingAnalytics(PDO $pdo) {
    $analytics = [];

    // Category Performance
    $stmtCat = $pdo->query("
        SELECT cc.name, SUM(c.collected_amount) as raised
        FROM campaigns c
        JOIN campaign_categories cc ON c.category_id = cc.id
        WHERE c.collected_amount > 0
        GROUP BY cc.id
        ORDER BY raised DESC
    ");
    $analytics['category_performance'] = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    // Goal vs Raised overall
    $stmtGoal = $pdo->query("SELECT SUM(target_amount) as goal, SUM(collected_amount) as raised FROM campaigns");
    $analytics['goal_vs_raised'] = $stmtGoal->fetch(PDO::FETCH_ASSOC);

    return $analytics;
}

function getSystemRecentActivity(PDO $pdo, $limit = 15) {
    // Massive Unified Stream now powered by Global Activity Logs
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(module, ' ', action) as title, 
            module, 
            created_at as event_date, 
            CASE 
                WHEN module = 'Authentication' THEN 'fas fa-sign-in-alt'
                WHEN module = 'Campaigns' THEN 'fas fa-bullhorn'
                WHEN module = 'Donation' THEN 'fas fa-hand-holding-heart'
                WHEN module = 'Tasks' THEN 'fas fa-tasks'
                WHEN module = 'Volunteer' THEN 'fas fa-user-plus'
                ELSE 'fas fa-info-circle' 
            END as icon,
            CASE 
                WHEN action = 'Login' THEN 'var(--info)'
                WHEN action = 'Create' OR action = 'Assign' THEN 'var(--primary)'
                WHEN action = 'Completed' THEN 'var(--success)'
                WHEN action = 'Delete' THEN 'var(--danger)'
                ELSE 'var(--text-muted)'
            END as color 
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT " . (int)$limit
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

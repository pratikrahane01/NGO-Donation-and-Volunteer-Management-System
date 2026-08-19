<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/coordinator_queries.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Event Coordinator (Role ID 5) can access
Middleware::role([5]);

// Initialize Database
$pdo = getDatabase();

$coordinator_id = $_SESSION['user_id'];
$kpis = get_coordinator_kpis($pdo, $coordinator_id);
$upcoming_events = get_coordinator_upcoming_events($pdo, $coordinator_id, 5);
$recent_activity = get_coordinator_recent_activity($pdo, $coordinator_id, 5);

?>
<?php 
$page_title = "Event Coordinator Dashboard";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="page-title">
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 5px;">Welcome Back,</p>
                    <h1>Event Coordinator Dashboard</h1>
                    <div class="breadcrumb" style="margin-top: 5px;">
                        <span style="color: var(--primary);">Dashboard</span>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(124,154,134,0.1); color: var(--primary);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['total_events']); ?></h3>
                        <p>My Total Events</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(43,60,54,0.1); color: var(--text-dark);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['upcoming_events']); ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(124,154,134,0.1); color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['total_volunteers']); ?></h3>
                        <p>My Volunteers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(43,60,54,0.1); color: var(--text-dark);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($kpis['todays_events']); ?></h3>
                        <p>Today's Events</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem;">
                <!-- Recent Activity -->
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div class="activity-feed" style="max-height: 300px; overflow-y: auto; padding-right: 10px;">
                        <?php if (empty($recent_activity)): ?>
                            <p style="text-align:center; color: var(--text-muted); margin-top: 2rem;">No recent activity.</p>
                        <?php else: ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach($recent_activity as $log): ?>
                                    <li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">
                                            <i class="far fa-clock"></i> <?php echo date('M d, g:i A', strtotime($log['date'])); ?>
                                        </div>
                                        <div style="font-weight: 500; color: var(--text-dark); margin-bottom: 3px;">
                                            <?php echo htmlspecialchars($log['description'] ?? ''); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--primary);">
                                            <?php echo htmlspecialchars($log['title'] ?? ''); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- My Upcoming Events -->
                <div class="glass-card" style="flex: 2; min-width: 600px;">
                    <div class="card-header">
                        <h3 class="card-title">My Upcoming Events</h3>
                        <a href="coordinator_events.php" style="color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 600;">View All</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($upcoming_events)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No upcoming events assigned.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($event['title'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($event['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars(ucfirst($event['status'] ?? 'planned')); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

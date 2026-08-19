<?php
// volunteer_dashboard.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/volunteer_queries.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];

// Fetch Data utilizing unified data loader and cache
$dashboardData = getVolunteerDashboardData($pdo, $volunteer_id);
$kpis = $dashboardData['kpis'];
$upcomingEvents = $dashboardData['upcomingEvents'];
$assignedTasks = $dashboardData['assignedTasks'];
$recentActivity = $dashboardData['recentActivity'];
$notifications = $dashboardData['notifications'];
?>
<?php 
$page_title = "Volunteer Dashboard";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            
            <div class="page-header">
                <div class="page-title">
                    <h1>Welcome, <?php echo htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? 'Volunteer'))[0]); ?>! 👋</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Here is your personal volunteer workspace.</p>
                </div>
            </div>

            <!-- First Row: KPIs -->
            <div class="kpi-grid">
                <?php 
                render_kpi_card('Assigned Events', $kpis['assigned_events'], 'fas fa-calendar-check', 'primary', 'volunteer_events.php');
                render_kpi_card('Upcoming Events', $kpis['upcoming_events'], 'far fa-calendar-alt', 'info', 'volunteer_events.php');
                render_kpi_card('Assigned Tasks', $kpis['assigned_tasks'], 'fas fa-tasks', 'warning', 'volunteer_tasks.php');
                render_kpi_card('Completed Tasks', $kpis['completed_tasks'], 'fas fa-check-circle', 'success', 'volunteer_tasks.php');
                render_kpi_card('Total Hours', $kpis['total_hours'], 'fas fa-clock', 'primary', 'volunteer_attendance.php');
                render_kpi_card('Attendance Rate', $kpis['attendance_percentage'].'%', 'fas fa-percentage', 'success', 'volunteer_attendance.php');
                ?>
            </div>

            <!-- Second Row: Tasks and Activity -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 2rem;">
                <!-- Assigned Tasks -->
                <div class="glass-card" style="flex: 2; min-width: 500px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">My Assigned Tasks</h3>
                        <a href="volunteer_tasks.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 600;">View All</a>
                    </div>
                    <?php if (empty($assignedTasks)): ?>
                        <?php render_empty_state('No Tasks', 'No pending tasks.', 'fas fa-clipboard-check'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Event</th>
                                        <th>Priority</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($assignedTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <a href="volunteer_tasks.php" style="text-decoration: none;">
                                                <strong style="color: var(--text-dark); display:block;"><?php echo htmlspecialchars($task['task_name'] ?? ''); ?></strong>
                                            </a>
                                        </td>
                                        <td><span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($task['event_title'] ?? ''); ?></span></td>
                                        <td>
                                            <?php 
                                            $prioClass = 'status-pending';
                                            if ($task['priority'] == 'high') $prioClass = 'status-inactive';
                                            if ($task['priority'] == 'low') $prioClass = 'status-active';
                                            ?>
                                            <span class="status-badge <?php echo $prioClass; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                        </td>
                                        <td><span style="font-size: 0.85rem; color: var(--text-muted);"><i class="far fa-calendar"></i> <?php echo date('M d', strtotime($task['deadline'])); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <?php if (empty($recentActivity)): ?>
                        <?php render_empty_state('No Activity', 'You have no recent activity.', 'fas fa-history'); ?>
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
            </div>

            <!-- Third Row: Upcoming Events & Notifications -->
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 2rem;">
                
                <!-- Upcoming Events -->
                <div class="glass-card" style="flex: 2; min-width: 500px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">My Upcoming Events</h3>
                        <a href="volunteer_events.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 600;">View All</a>
                    </div>
                    <?php if (empty($upcomingEvents)): ?>
                        <?php render_empty_state('No Upcoming Events', 'No events have been assigned yet.', 'far fa-calendar-times'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Coordinator</th>
                                        <th>Date & Time</th>
                                        <th>Venue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($upcomingEvents as $evt): ?>
                                    <tr>
                                        <td>
                                            <a href="volunteer_events.php" style="text-decoration: none;">
                                                <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($evt['title'] ?? ''); ?></strong>
                                            </a>
                                        </td>
                                        <td><span style="font-size: 0.85rem; font-weight: 600; color: var(--primary-dark);"><?php echo htmlspecialchars($evt['coordinator_name'] ?? ''); ?></span></td>
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

                <!-- Unread Notifications -->
                <div class="glass-card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3 class="card-title">Unread Notifications</h3>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <?php render_empty_state('All caught up!', 'You\'re all caught up.', 'far fa-bell-slash'); ?>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach($notifications as $notif): ?>
                                <li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
                                    <a href="volunteer_notifications.php" style="text-decoration: none; color: inherit; display: flex; gap: 15px;">
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

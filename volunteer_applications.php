<?php
// volunteer_applications.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];

// Fetch Applications
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.title as event_title, 
               vr.registration_date as applied_date, 
               vr.approval_status 
        FROM volunteer_registrations vr 
        JOIN events e ON vr.event_id = e.id 
        WHERE vr.volunteer_id = ? 
        ORDER BY vr.registration_date DESC
    ");
    $stmt->execute([$volunteer_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Volunteer Applications Error: " . $e->getMessage());
}
?>
<?php 
$page_title = "My Applications";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>My Applications</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Track your event registrations</p>
                </div>
            </div>

            <div class="glass-card">
                <?php if (empty($applications)): ?>
                    <?php render_empty_state('No Applications', 'You have not applied for any events yet.', 'fas fa-file-signature'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Applied Date</th>
                                    <th>Application Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($app['event_title'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                                            <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-pending'; // default for pending
                                        if ($app['approval_status'] == 'approved') {
                                            $statusClass = 'status-active'; // green
                                        } elseif ($app['approval_status'] == 'rejected') {
                                            $statusClass = 'status-inactive'; // red
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($app['approval_status'] ?? '')); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

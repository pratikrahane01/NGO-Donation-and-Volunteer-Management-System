<?php
// volunteer_tasks.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Task Completion/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['status'];
    if (in_array($new_status, ['in_progress', 'completed'])) {
        try {
            // Verify task belongs to this volunteer and is not already completed
            $stmt = $pdo->prepare("SELECT id, event_id, task_name FROM tasks WHERE id = ? AND volunteer_id = ? AND completion_status != 'completed'");
            $stmt->execute([$task_id, $volunteer_id]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $update = $pdo->prepare("UPDATE tasks SET completion_status = ? WHERE id = ?");
                $update->execute([$new_status, $task_id]);
                
                if ($new_status === 'completed') {
                    // Notify Coordinator
                    $ev = $pdo->prepare("SELECT coordinator_id FROM events WHERE id = ?");
                    $ev->execute([$row['event_id']]);
                    $coord_id = $ev->fetchColumn();
                    if ($coord_id) {
                        $nMsg = "Volunteer " . $_SESSION['full_name'] . " has completed the task: " . $row['task_name'];
                        $nStmt = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type) VALUES (?, 5, 'Task Completed', ?, 'System')");
                        $nStmt->execute([$coord_id, $nMsg]);
                    }
                }
                
                $success_msg = "Task status updated successfully!";
            } else {
                $error_msg = "Invalid task or already completed.";
            }
        } catch (PDOException $e) {
            $error_msg = "An error occurred while updating the task.";
            error_log("Volunteer Task Update Error: " . $e->getMessage());
        }
    }
}

// Fetch all tasks for this volunteer
$tasks = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, e.title as event_title 
        FROM tasks t
        JOIN events e ON t.event_id = e.id
        JOIN volunteer_registrations vr ON t.event_id = vr.event_id AND t.volunteer_id = vr.volunteer_id
        WHERE t.volunteer_id = ? AND vr.approval_status = 'approved'
        ORDER BY FIELD(t.completion_status, 'pending', 'in_progress', 'completed'), t.deadline ASC, FIELD(t.priority, 'high', 'medium', 'low')
    ");
    $stmt->execute([$volunteer_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Volunteer Tasks Fetch Error: " . $e->getMessage());
}
?>
<?php 
$page_title = "My Tasks";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Pending Tasks</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Manage your assigned event tasks.</p>
                </div>
            </div>

            <div class="glass-card">
                <?php if ($success_msg): ?>
                    <div style="padding: 15px; background: rgba(16,185,129,0.1); color: var(--success); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div style="padding: 15px; background: rgba(239,68,68,0.1); color: var(--danger); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <?php if (empty($tasks)): ?>
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
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tasks as $task): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-dark); display: block;"><?php echo htmlspecialchars($task['task_name'] ?? ''); ?></strong>
                                        <?php if (!empty($task['description'])): ?>
                                            <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($task['description'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 600;"><?php echo htmlspecialchars($task['event_title'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $prioClass = 'status-pending';
                                        if ($task['priority'] == 'high') $prioClass = 'status-inactive';
                                        if ($task['priority'] == 'low') $prioClass = 'status-active';
                                        ?>
                                        <span class="status-badge <?php echo $prioClass; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);"><i class="far fa-calendar"></i> <?php echo $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'No Deadline'; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-pending';
                                        if ($task['completion_status'] == 'completed') $statusClass = 'status-active';
                                        if ($task['completion_status'] == 'in_progress') $statusClass = 'status-warning';
                                        if ($task['completion_status'] == 'submitted_for_review') $statusClass = 'status-inactive';
                                        if ($task['completion_status'] == 'needs_revision') $statusClass = 'status-danger';
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $task['completion_status'])); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($task['completion_status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id'] ?? ''; ?>">
                                                <input type="hidden" name="status" value="in_progress">
                                                <button type="submit" class="btn-primary" style="padding: 5px 12px; font-size: 0.75rem; border: none; cursor: pointer; background: var(--warning);"><i class="fas fa-spinner"></i> Start</button>
                                            </form>
                                        <?php elseif ($task['completion_status'] === 'in_progress'): ?>
                                            <a href="volunteer_task_submit.php?id=<?php echo $task['id'] ?? ''; ?>" class="btn-primary" style="padding: 5px 12px; font-size: 0.75rem; text-decoration: none; display: inline-block; background: var(--success);"><i class="fas fa-upload"></i> Submit Work</a>
                                        <?php elseif ($task['completion_status'] === 'needs_revision'): ?>
                                            <a href="volunteer_task_submit.php?id=<?php echo $task['id'] ?? ''; ?>" class="btn-primary" style="padding: 5px 12px; font-size: 0.75rem; text-decoration: none; display: inline-block; background: var(--danger);"><i class="fas fa-edit"></i> Edit Submission</a>
                                        <?php elseif ($task['completion_status'] === 'submitted_for_review'): ?>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-hourglass-half"></i> In Review</span>
                                        <?php else: ?>
                                            <span style="color: var(--success); font-size: 1.2rem;"><i class="fas fa-check-circle"></i></span>
                                        <?php endif; ?>
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

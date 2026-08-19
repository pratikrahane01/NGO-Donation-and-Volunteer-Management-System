<?php
// coordinator_task_submissions.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Event Coordinator (Role ID 5) can access
Middleware::role([5]);

$pdo = getDatabase();
$coordinator_id = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $sub_id = filter_var($_POST['submission_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    $feedback = trim($_POST['feedback'] ?? '');

    if ($sub_id) {
        // Verify ownership
        $check = $pdo->prepare("
            SELECT ts.*, t.task_name, e.coordinator_id, u.full_name as volunteer_name
            FROM task_submissions ts
            JOIN tasks t ON ts.task_id = t.id
            JOIN events e ON t.event_id = e.id
            JOIN users u ON ts.volunteer_id = u.id
            WHERE ts.id = ? AND e.coordinator_id = ?
        ");
        $check->execute([$sub_id, $coordinator_id]);
        $subData = $check->fetch(PDO::FETCH_ASSOC);

        if ($subData) {
            try {
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    $pdo->prepare("UPDATE task_submissions SET status = 'approved', coordinator_feedback = ? WHERE id = ?")->execute([$feedback, $sub_id]);
                    $pdo->prepare("UPDATE tasks SET completion_status = 'completed' WHERE id = ?")->execute([$subData['task_id']]);
                    
                    // Notification for volunteer
                    $notify = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type) VALUES (?, 4, 'Task Approved', ?, 'task_approved')");
                    $msg = "Your submission for task '{$subData['task_name']}' has been approved!";
                    $notify->execute([$subData['volunteer_id'], $msg]);

                    // Activity log
                    $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (?, ?, 'Tasks', ?)")->execute([$coordinator_id, "Approved task submission #{$sub_id}", $_SERVER['REMOTE_ADDR']]);

                    $success_msg = "Task submission approved successfully.";

                } elseif ($action === 'request_revision') {
                    if (empty($feedback)) {
                        throw new Exception("Feedback is required when requesting a revision.");
                    }
                    $pdo->prepare("UPDATE task_submissions SET status = 'needs_revision', coordinator_feedback = ? WHERE id = ?")->execute([$feedback, $sub_id]);
                    $pdo->prepare("UPDATE tasks SET completion_status = 'needs_revision' WHERE id = ?")->execute([$subData['task_id']]);
                    
                    // Notification for volunteer
                    $notify = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type) VALUES (?, 4, 'Revision Requested', ?, 'task_revision')");
                    $msg = "Revision requested for task '{$subData['task_name']}'. Feedback: " . substr($feedback, 0, 100) . "...";
                    $notify->execute([$subData['volunteer_id'], $msg]);

                    $success_msg = "Revision requested successfully.";
                }

                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = $e->getMessage();
            }
        } else {
            $error_msg = "Submission not found or permission denied.";
        }
    }
}

// Fetch pending submissions
$stmt = $pdo->prepare("
    SELECT ts.*, t.task_name, e.title as event_title, u.full_name as volunteer_name, u.email as volunteer_email, u.profile_image
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN events e ON t.event_id = e.id
    JOIN users u ON ts.volunteer_id = u.id
    WHERE e.coordinator_id = ? AND ts.status = 'submitted_for_review'
    ORDER BY ts.created_at DESC
");
$stmt->execute([$coordinator_id]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php 
$page_title = "Task Submissions";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header" style="margin-bottom: 30px;">
                <div class="page-title">
                    <h1>Task Submissions</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Review work submitted by volunteers.</p>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div style="padding: 15px; background: rgba(16,185,129,0.1); color: var(--success); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div style="padding: 15px; background: rgba(239,68,68,0.1); color: var(--danger); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <?php if (empty($submissions)): ?>
                <?php render_empty_state('No Pending Submissions', 'There are no task submissions awaiting your review.', 'fas fa-clipboard-check'); ?>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                    <?php foreach ($submissions as $sub): ?>
                        <div class="submission-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <?php 
                                    $profile_img = $sub['profile_image'] ? 'uploads/profiles/' . htmlspecialchars($sub['profile_image'] ?? '') : 'assets/images/default-avatar.png';
                                    ?>
                                    <img src="<?php echo $profile_img; ?>" alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-dark);"><?php echo htmlspecialchars($sub['volunteer_name'] ?? ''); ?></h3>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">
                                            <i class="far fa-clock"></i> Submitted: <?php echo date('M d, Y h:i A', strtotime($sub['submitted_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="display: block; color: var(--primary); font-size: 1.1rem;"><?php echo htmlspecialchars($sub['task_name'] ?? ''); ?></strong>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($sub['event_title'] ?? ''); ?></span>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <h4 style="margin: 0 0 10px 0; font-size: 1rem; color: var(--text-dark);">Work Summary</h4>
                                <p style="margin: 0; color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px;">
                                    <?php echo nl2br(htmlspecialchars($sub['summary'] ?? '')); ?>
                                </p>
                            </div>

                            <?php if ($sub['hours_contributed'] || $sub['challenges_faced'] || $sub['suggestions']): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                    <?php if ($sub['hours_contributed']): ?>
                                        <div>
                                            <strong style="display: block; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 5px;">Hours Contributed</strong>
                                            <span style="color: var(--primary); font-weight: 600; font-size: 1.2rem;"><?php echo htmlspecialchars($sub['hours_contributed'] ?? ''); ?> <small style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">hrs</small></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($sub['challenges_faced']): ?>
                                        <div>
                                            <strong style="display: block; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 5px;">Challenges Faced</strong>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars($sub['challenges_faced'] ?? ''); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($sub['suggestions']): ?>
                                        <div>
                                            <strong style="display: block; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 5px;">Suggestions</strong>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars($sub['suggestions'] ?? ''); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php 
                            $paths = json_decode($sub['proof_file_paths'], true);
                            if (is_array($paths) && !empty($paths)): 
                            ?>
                                <div style="margin-bottom: 20px;">
                                    <h4 style="margin: 0 0 10px 0; font-size: 0.9rem; color: var(--text-dark);">Proof Files</h4>
                                    <div>
                                        <?php foreach ($paths as $path): ?>
                                            <a href="<?php echo htmlspecialchars($path); ?>" target="_blank" class="proof-file">
                                                <i class="fas fa-file-download"></i> <?php echo basename($path); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div style="border-top: 1px solid var(--border-color); padding-top: 15px; margin-top: 15px;">
                                <form method="POST" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id'] ?? ''; ?>">
                                    <div style="flex: 1; min-width: 250px;">
                                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px;">Feedback (Required for Revision)</label>
                                        <textarea name="feedback" rows="2" class="modern-input form-control" placeholder="Enter feedback or praise here..." style="resize: vertical;"></textarea>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" name="action" value="request_revision" class="btn-primary" style="background: var(--warning); padding: 10px 20px;">
                                            <i class="fas fa-undo"></i> Request Revision
                                        </button>
                                        <button type="submit" name="action" value="approve" class="btn-primary" style="background: var(--success); padding: 10px 20px;">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

<?php
// volunteer_task_submit.php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';

// Protect this dashboard: Only Volunteer (Role ID 4) can access
Middleware::role([4]);

$pdo = getDatabase();
$volunteer_id = $_SESSION['user_id'];

$task_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
if (!$task_id) {
    header("Location: volunteer_tasks.php");
    exit;
}

// Fetch task and ensure ownership
$taskStmt = $pdo->prepare("
    SELECT t.*, e.title as event_title, e.coordinator_id 
    FROM tasks t 
    JOIN events e ON t.event_id = e.id 
    WHERE t.id = ? AND t.volunteer_id = ?
");
$taskStmt->execute([$task_id, $volunteer_id]);
$task = $taskStmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Task not found or permission denied.");
}

if (!in_array($task['completion_status'], ['in_progress', 'needs_revision'])) {
    die("This task cannot be submitted at this time.");
}

// Fetch existing submission if it's needs_revision
$submission = null;
if ($task['completion_status'] === 'needs_revision') {
    $subStmt = $pdo->prepare("SELECT * FROM task_submissions WHERE task_id = ? ORDER BY id DESC LIMIT 1");
    $subStmt->execute([$task_id]);
    $submission = $subStmt->fetch(PDO::FETCH_ASSOC);
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $summary = trim($_POST['summary'] ?? '');
    $hours = !empty($_POST['hours_contributed']) ? floatval($_POST['hours_contributed']) : null;
    $challenges = trim($_POST['challenges_faced'] ?? '');
    $suggestions = trim($_POST['suggestions'] ?? '');
    
    // Validation
    if (strlen($summary) < 30 || strlen($summary) > 1000) {
        $error_msg = "Work Summary must be between 30 and 1000 characters.";
    }

    $uploaded_paths = [];
    if (empty($error_msg)) {
        // File upload logic
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'video/mp4'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $files = $_FILES['proof_files'] ?? null;
        
        if ($files && isset($files['name'][0]) && !empty($files['name'][0])) {
            $total_files = count($files['name']);
            if ($total_files > 5) {
                $error_msg = "You can upload a maximum of 5 files.";
            } else {
                for ($i = 0; $i < $total_files; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $files['tmp_name'][$i];
                        $size = $files['size'][$i];
                        
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmp_name);
                        finfo_close($finfo);
                        
                        if (!in_array($mime, $allowedTypes)) {
                            $error_msg = "File type not allowed for file: " . htmlspecialchars($files['name'][$i]);
                            break;
                        }
                        if ($size > $maxSize) {
                            $error_msg = "File exceeds 10MB limit: " . htmlspecialchars($files['name'][$i]);
                            break;
                        }
                        
                        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                        $new_filename = uniqid('proof_') . '_' . time() . '.' . $ext;
                        $dest = __DIR__ . '/uploads/tasks/' . $new_filename;
                        
                        if (move_uploaded_file($tmp_name, $dest)) {
                            $uploaded_paths[] = 'uploads/tasks/' . $new_filename;
                        } else {
                            $error_msg = "Failed to move uploaded file: " . htmlspecialchars($files['name'][$i]);
                            break;
                        }
                    } else if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                        $error_msg = "Error uploading file: " . htmlspecialchars($files['name'][$i]);
                        break;
                    }
                }
            }
        }
    }

    if (empty($error_msg)) {
        try {
            $pdo->beginTransaction();
            
            // If editing a revision, we could keep existing paths if no new ones are provided.
            if ($submission && empty($uploaded_paths) && !empty($submission['proof_file_paths'])) {
                $paths_json = $submission['proof_file_paths'];
            } else {
                $paths_json = json_encode($uploaded_paths);
            }

            if ($submission) {
                // Update existing submission
                $stmt = $pdo->prepare("UPDATE task_submissions SET summary = ?, hours_contributed = ?, challenges_faced = ?, suggestions = ?, proof_file_paths = ?, status = 'submitted_for_review' WHERE id = ?");
                $stmt->execute([$summary, $hours, $challenges, $suggestions, $paths_json, $submission['id']]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO task_submissions (task_id, volunteer_id, summary, hours_contributed, challenges_faced, suggestions, proof_file_paths, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted_for_review')");
                $stmt->execute([$task_id, $volunteer_id, $summary, $hours, $challenges, $suggestions, $paths_json]);
            }

            // Update task status
            $pdo->prepare("UPDATE tasks SET completion_status = 'submitted_for_review' WHERE id = ?")->execute([$task_id]);

            // Notify coordinator
            $notify = $pdo->prepare("INSERT INTO notifications (recipient_id, role_id, title, message, notification_type) VALUES (?, 5, 'New Task Submission', ?, 'task_submitted')");
            $msg = $_SESSION['user_name'] . " submitted work for task '" . $task['task_name'] . "'.";
            $notify->execute([$task['coordinator_id'], $msg]);

            $pdo->commit();
            $success_msg = "Work submitted successfully!";
            header("Location: volunteer_tasks.php?msg=" . urlencode($success_msg));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<?php 
$page_title = "Submit Work";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>

        <div class="page-content">
            <div class="page-header" style="margin-bottom: 30px;">
                <div class="page-title">
                    <a href="volunteer_tasks.php" style="color: var(--primary); text-decoration: none; font-size: 0.9rem; margin-bottom: 10px; display: inline-block;"><i class="fas fa-arrow-left"></i> Back to Tasks</a>
                    <h1>Submit Work</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Task: <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($task['task_name'] ?? ''); ?></strong> (<?php echo htmlspecialchars($task['event_title'] ?? ''); ?>)</p>
                </div>
            </div>

            <?php if ($error_msg): ?>
                <div style="padding: 15px; background: rgba(239,68,68,0.1); color: var(--danger); border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <?php if ($submission && ($submission['status'] ?? '') === 'needs_revision' && !empty($submission['coordinator_feedback'])): ?>
                <div style="padding: 20px; background: rgba(245,158,11,0.1); border-left: 4px solid var(--warning); border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin:0 0 10px 0; color: #b45309;"><i class="fas fa-exclamation-triangle"></i> Revision Requested</h3>
                    <p style="margin:0; color: #92400e; font-size: 0.95rem;"><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($submission['coordinator_feedback'] ?? '')); ?></p>
                </div>
            <?php endif; ?>

            <div class="glass-card" style="max-width: 800px;">
                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 20px;">
                        <label for="summary" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Work Summary <span style="color: var(--danger);">*</span></label>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0; margin-bottom: 10px;">Describe what was completed (30 - 1000 characters).</p>
                        <textarea name="summary" id="summary" required minlength="30" maxlength="1000" rows="5" class="modern-input form-control" style="resize: vertical;"><?php echo $submission ? htmlspecialchars($submission['summary'] ?? '') : ''; ?></textarea>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label for="hours_contributed" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Hours Contributed (Optional)</label>
                            <input type="number" step="0.1" min="0" name="hours_contributed" id="hours_contributed" class="modern-input" value="<?php echo $submission ? htmlspecialchars($submission['hours_contributed'] ?? '') : ''; ?>">
                        </div>
                        <div style="flex: 1;"></div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label for="challenges_faced" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Challenges Faced (Optional)</label>
                        <textarea name="challenges_faced" id="challenges_faced" rows="3" class="modern-input form-control" style="resize: vertical;"><?php echo $submission ? htmlspecialchars($submission['challenges_faced'] ?? '') : ''; ?></textarea>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label for="suggestions" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Suggestions (Optional)</label>
                        <textarea name="suggestions" id="suggestions" rows="3" class="modern-input form-control" style="resize: vertical;"><?php echo $submission ? htmlspecialchars($submission['suggestions'] ?? '') : ''; ?></textarea>
                    </div>

                    <div style="margin-bottom: 30px; padding: 20px; border: 1px dashed var(--border-color); border-radius: 12px; background: rgba(0,0,0,0.02);">
                        <label for="proof_files" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Upload Proof Files (Optional)</label>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0; margin-bottom: 15px;">You can upload up to 5 files (.jpg, .jpeg, .png, .pdf, .mp4). Max 10MB per file.</p>
                        <input type="file" name="proof_files[]" id="proof_files" multiple accept="image/jpeg,image/png,application/pdf,video/mp4" class="modern-input" style="padding: 10px; background: white;">
                        
                        <?php if ($submission && !empty($submission['proof_file_paths'])): ?>
                            <div style="margin-top: 15px; font-size: 0.9rem;">
                                <strong>Previously Uploaded Files:</strong><br>
                                <?php 
                                $paths = json_decode($submission['proof_file_paths'], true);
                                if (is_array($paths)) {
                                    foreach ($paths as $path) {
                                        echo '<a href="'.htmlspecialchars($path).'" target="_blank" style="display:inline-block; margin-right: 10px; color: var(--primary);"><i class="fas fa-paperclip"></i> '.basename($path).'</a>';
                                    }
                                }
                                ?>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">Note: Uploading new files will replace these existing files.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-primary" style="padding: 12px 24px; font-size: 1rem;"><i class="fas fa-paper-plane"></i> Submit Work</button>
                </form>
            </div>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

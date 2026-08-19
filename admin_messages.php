<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';

// Super Admin or NGO Admin
Middleware::role([1, 2]);

$pdo = getDatabase();
$messages = [];
try {
    $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<?php 
$page_title = "Contact Messages";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Contact Messages</h1>
                </div>
            </div>
            
            <div class="glass-card">
                <?php if (empty($messages)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 40px;">No messages found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($messages as $msg): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($msg['name'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars($msg['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($msg['subject'] ?? ''); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></td>
                                    <td><span class="status-badge <?php echo ($msg['status'] ?? '') === 'new' ? 'status-pending' : 'status-active'; ?>"><?php echo ucfirst($msg['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

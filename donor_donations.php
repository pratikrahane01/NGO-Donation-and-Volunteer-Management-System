<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/includes/dashboard/components.php';
require_once __DIR__ . '/includes/dashboard/donor_queries.php';

Middleware::role([3]);

$pdo = getDatabase();
$donor_id = $_SESSION['user_id'];
$donations = getDonationHistory($pdo, $donor_id, 100);

?>
<?php 
$page_title = "My Donations";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <div class="page-header">
                <div class="page-title">
                    <h1>My Donations</h1>
                    <p style="color: var(--text-muted); margin-top: 5px;">Track your contributions and access your receipts.</p>
                </div>
            </div>

            <div class="glass-card">
                <?php if (empty($donations)): ?>
                    <?php render_empty_state('No Donations', 'No donations yet.', 'fas fa-hand-holding-heart'); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Transaction Details</th>
                                    <th>Campaign</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($donations as $don): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-dark); display: block;"><?php echo htmlspecialchars($don['transaction_id'] ?? ''); ?></strong>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                                            <i class="far fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($don['donation_date'])); ?> 
                                            &bull; <?php echo htmlspecialchars($don['payment_method'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($don['campaign_id']): ?>
                                            <a href="donor_campaign_details.php?id=<?php echo $don['campaign_id'] ?? ''; ?>" style="text-decoration: none; color: var(--text-dark); font-weight: 600;">
                                                <?php echo htmlspecialchars($don['campaign_name'] ?? ''); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">General Fund</span>
                                        <?php endif; ?>
                                        
                                        <?php if($don['is_anonymous']): ?>
                                            <div style="font-size: 0.75rem; color: var(--warning); margin-top: 4px;"><i class="fas fa-user-secret"></i> Anonymous</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--success); font-size: 1.1rem;"><?php echo formatIndianCurrency($don['amount']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $badgeClass = 'status-pending';
                                        if ($don['payment_status'] === 'completed') $badgeClass = 'status-active';
                                        if ($don['payment_status'] === 'failed') $badgeClass = 'status-inactive';
                                        ?>
                                        <span class="status-badge <?php echo $badgeClass; ?>"><?php echo ucfirst($don['payment_status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($don['payment_status'] === 'completed' && !empty($don['receipt_number'])): ?>
                                            <a href="donor_receipts.php?receipt=<?php echo htmlspecialchars($don['receipt_number'] ?? ''); ?>" class="btn-secondary" style="padding: 5px 12px; font-size: 0.8rem; text-decoration: none;">
                                                <i class="fas fa-file-invoice"></i> Receipt
                                            </a>
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

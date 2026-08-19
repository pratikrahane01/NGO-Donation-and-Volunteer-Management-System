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

$receiptNum = $_GET['receipt'] ?? null;
$viewReceipt = null;

if ($receiptNum) {
    // Validate receipt belongs to this donor
    $stmt = $pdo->prepare("
        SELECT r.*, d.amount, d.transaction_id, d.payment_method, d.donation_date, c.name as campaign_name, u.full_name as donor_name, u.email as donor_email
        FROM donation_receipts r
        JOIN donations d ON r.donation_id = d.id
        LEFT JOIN campaigns c ON d.campaign_id = c.id
        JOIN users u ON d.donor_id = u.id
        WHERE r.receipt_number = ? AND d.donor_id = ?
    ");
    $stmt->execute([$receiptNum, $donor_id]);
    $viewReceipt = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // List all receipts
    $stmt = $pdo->prepare("
        SELECT r.*, d.amount, c.name as campaign_name
        FROM donation_receipts r
        JOIN donations d ON r.donation_id = d.id
        LEFT JOIN campaigns c ON d.campaign_id = c.id
        WHERE d.donor_id = ?
        ORDER BY r.generated_date DESC
    ");
    $stmt->execute([$donor_id]);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php 
$page_title = "";
require_once __DIR__ . '/includes/dashboard/layout_header.php'; 
?>
        <div class="page-content">
            
            <?php if ($viewReceipt): ?>
                <style>
                    .invoice-card {
                        background: var(--surface);
                        border-radius: var(--radius-xl);
                        padding: 3rem;
                        border: 1px solid var(--border-light);
                        box-shadow: var(--shadow-sm);
                        position: relative;
                        overflow: hidden;
                        max-width: 800px;
                        margin: 0 auto;
                        z-index: 1;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    /* Top Gradient Border */
                    .invoice-card::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        height: 6px;
                        background: linear-gradient(90deg, var(--primary), var(--secondary));
                        z-index: 2;
                    }
                    /* Watermark */
                    .invoice-card::after {
                        content: '';
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 70%;
                        height: 70%;
                        background-image: url('<?php echo htmlspecialchars(defined("APP_LOGO_PATH") ? APP_LOGO_PATH : "assets/images/logo/arohan-logo.jpeg"); ?>');
                        background-repeat: no-repeat;
                        background-position: center;
                        background-size: contain;
                        opacity: 0.04;
                        z-index: -1;
                        pointer-events: none;
                    }
                    .invoice-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 3rem;
                        padding-bottom: 2rem;
                        border-bottom: 1px solid var(--border-light);
                    }
                    .invoice-brand {
                        display: flex;
                        align-items: center;
                        gap: 1rem;
                    }
                    .invoice-brand img {
                        width: 50px;
                        height: 50px;
                        object-fit: contain;
                    }
                    .invoice-brand h2 {
                        margin: 0;
                        font-size: var(--h4-size);
                        color: var(--text-primary);
                        font-weight: 700;
                    }
                    .invoice-meta {
                        text-align: right;
                    }
                    .invoice-meta h3 {
                        margin: 0 0 0.5rem 0;
                        color: var(--text-secondary);
                        font-size: var(--h5-size);
                        font-weight: 600;
                    }
                    .invoice-meta p {
                        margin: 0;
                        color: var(--text-tertiary);
                        font-size: var(--small-size);
                    }
                    .invoice-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 2rem;
                        margin-bottom: 3rem;
                    }
                    .invoice-section-title {
                        font-size: var(--caption-size);
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                        color: var(--text-tertiary);
                        margin-bottom: 0.5rem;
                        font-weight: 600;
                    }
                    .invoice-info-block p {
                        margin: 0;
                        color: var(--text-primary);
                        font-weight: 500;
                        font-size: var(--body-size);
                        line-height: 1.5;
                    }
                    .invoice-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 2rem;
                    }
                    .invoice-table th {
                        text-align: left;
                        padding: 1rem;
                        background: var(--surface-hover);
                        color: var(--text-secondary);
                        font-size: var(--caption-size);
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                        font-weight: 600;
                        border-radius: var(--radius-md) var(--radius-md) 0 0;
                    }
                    .invoice-table td {
                        padding: 1.5rem 1rem;
                        border-bottom: 1px solid var(--border-light);
                        color: var(--text-primary);
                        font-size: var(--body-size);
                    }
                    .invoice-summary {
                        display: flex;
                        justify-content: flex-end;
                        margin-bottom: 3rem;
                    }
                    .invoice-summary-box {
                        background: var(--surface-hover);
                        padding: 1.5rem 2rem;
                        border-radius: var(--radius-lg);
                        min-width: 300px;
                    }
                    .invoice-summary-row {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 0.5rem;
                        color: var(--text-secondary);
                        font-size: var(--body-size);
                    }
                    .invoice-summary-total {
                        display: flex;
                        justify-content: space-between;
                        margin-top: 1rem;
                        padding-top: 1rem;
                        border-top: 2px dashed var(--border-dark);
                        color: var(--primary);
                        font-weight: 700;
                        font-size: var(--h4-size);
                    }
                    .invoice-footer {
                        text-align: center;
                        color: var(--text-tertiary);
                        font-size: var(--caption-size);
                        border-top: 1px solid var(--border-light);
                        padding-top: 2rem;
                    }
                    @media print {
                        @page {
                            margin: 10mm;
                        }
                        body * { visibility: hidden; }
                        .invoice-card, .invoice-card * { visibility: visible; }
                        .invoice-card { 
                            position: absolute; 
                            left: 0; 
                            top: 0; 
                            width: 100%; 
                            box-shadow: none; 
                            border: none; 
                            padding: 2rem; 
                            max-width: none; 
                            margin: 0; 
                            background: transparent;
                        }
                        .print-hide { display: none !important; }
                    }
                </style>

                <div class="print-hide" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; max-width: 800px; margin-left: auto; margin-right: auto;">
                    <a href="donor_receipts.php" class="btn btn-ghost">
                        <i class="fas fa-arrow-left"></i> Back to Receipts
                    </a>
                    <div style="display: flex; gap: 12px;">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                    </div>
                </div>

                <div class="invoice-card">
                    <div class="invoice-header">
                        <div class="invoice-brand">
                            <?php 
                            $logoPath = defined('APP_LOGO_PATH') ? APP_LOGO_PATH : 'assets/images/logo/arohan-logo.jpeg';
                            if (file_exists(__DIR__ . '/' . $logoPath)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="<?php echo htmlspecialchars(APP_NAME); ?> Logo">
                                <h2><?php echo htmlspecialchars(APP_NAME); ?></h2>
                            <?php else: ?>
                                <h2><?php echo htmlspecialchars(APP_NAME); ?></h2>
                            <?php endif; ?>
                        </div>
                        <div class="invoice-meta">
                            <h3>Donation Receipt</h3>
                            <p><strong>Receipt #:</strong> <?php echo htmlspecialchars($viewReceipt['receipt_number'] ?? ''); ?></p>
                            <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($viewReceipt['generated_date'])); ?></p>
                        </div>
                    </div>

                    <div class="invoice-grid">
                        <div class="invoice-info-block">
                            <div class="invoice-section-title">Received From</div>
                            <p><?php echo htmlspecialchars($viewReceipt['donor_name'] ?? ''); ?></p>
                            <p style="color: var(--text-secondary); font-size: var(--small-size);"><?php echo htmlspecialchars($viewReceipt['donor_email'] ?? ''); ?></p>
                        </div>
                        <div class="invoice-info-block" style="text-align: right;">
                            <div class="invoice-section-title">Payment Info</div>
                            <p>Transaction ID: <?php echo htmlspecialchars($viewReceipt['transaction_id'] ?? ''); ?></p>
                            <p>Method: <?php echo htmlspecialchars($viewReceipt['payment_method'] ?? ''); ?></p>
                        </div>
                    </div>

                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Donation to Campaign</strong><br>
                                    <span style="color: var(--text-secondary); font-size: var(--small-size);"><?php echo htmlspecialchars($viewReceipt['campaign_name'] ?? 'General Fund'); ?></span>
                                </td>
                                <td style="text-align: right; font-weight: 500;"><?php echo formatIndianCurrency($viewReceipt['amount']); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="invoice-summary">
                        <div class="invoice-summary-box">
                            <div class="invoice-summary-row">
                                <span>Subtotal</span>
                                <span><?php echo formatIndianCurrency($viewReceipt['amount']); ?></span>
                            </div>
                            <div class="invoice-summary-row">
                                <span>Status</span>
                                <span style="color: var(--success); font-weight: 600;">Completed</span>
                            </div>
                            <div class="invoice-summary-total">
                                <span>Total Paid</span>
                                <span><?php echo formatIndianCurrency($viewReceipt['amount']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="invoice-footer">
                        <p>Thank you for your generous contribution. This receipt is computer generated and does not require a physical signature.</p>
                        <p style="font-weight: 500; margin-top: 8px;">All donations are tax-deductible to the extent permitted by law.</p>
                    </div>
                </div>

            <?php else: ?>
                <div class="page-header">
                    <div class="page-title">
                        <h1>My Receipts</h1>
                        <p style="color: var(--text-muted); margin-top: 5px;">View and download your official donation receipts.</p>
                    </div>
                </div>

                <div class="glass-card">
                    <?php if (empty($receipts)): ?>
                        <?php render_empty_state('No Receipts', 'No receipts available.', 'fas fa-file-invoice-dollar'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Receipt Number</th>
                                        <th>Date Generated</th>
                                        <th>Campaign</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($receipts as $r): ?>
                                    <tr>
                                        <td>
                                            <a href="donor_receipts.php?receipt=<?php echo urlencode($r['receipt_number']); ?>" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                                <i class="fas fa-file-pdf" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($r['receipt_number'] ?? ''); ?>
                                            </a>
                                        </td>
                                        <td><span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($r['generated_date'])); ?></span></td>
                                        <td><span style="color: var(--text-dark);"><?php echo htmlspecialchars($r['campaign_name'] ?? 'General Fund'); ?></span></td>
                                        <td><strong style="color: var(--success);"><?php echo formatIndianCurrency($r['amount']); ?></strong></td>
                                        <td>
                                            <a href="donor_receipts.php?receipt=<?php echo urlencode($r['receipt_number']); ?>" class="btn-secondary" style="padding: 5px 12px; font-size: 0.8rem; text-decoration: none;">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    
<?php require_once __DIR__ . '/includes/dashboard/layout_footer.php'; ?>

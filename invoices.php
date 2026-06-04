<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
ensureLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

$job = $_GET['job'] ?? '';
if (!$job) die('Missing job id.');

$stmt = $pdo->prepare('SELECT * FROM invoices WHERE job_card_id = ? LIMIT 1');
$stmt->execute([$job]);
$inv = $stmt->fetch();

if (!$inv) die('Invoice not found.');

$job_stmt = $pdo->prepare('SELECT j.*, v.brand, v.model, v.customer_id, u.name AS customer_name FROM job_cards j JOIN vehicles v ON j.vehicle_no = v.vehicle_no JOIN users u ON v.customer_id = u.id WHERE j.job_card_id = ? LIMIT 1');
$job_stmt->execute([$job]);
$jobrow = $job_stmt->fetch();

// Authorization: allow if mechanic, admin, or the owning customer
if (!($userRole === 'ADMIN' || $userRole === 'MECHANIC' || ($userRole === 'CUSTOMER' && intval($jobrow['customer_id']) === intval($userId)))) {
    die('Unauthorized to view this invoice.');
}

// Handle payment action by customer (simulated)
$pay_success = '';
$pay_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_invoice') {
    if ($userRole !== 'CUSTOMER' || intval($jobrow['customer_id']) !== intval($userId)) {
        $pay_error = 'Unauthorized payment attempt.';
    } else {
        $method = $_POST['payment_method'] ?? 'UPI';
        $upd = $pdo->prepare('UPDATE invoices SET payment_status = ?, payment_method = ? WHERE invoice_no = ?');
        $upd->execute(['PAID', $method, $inv['invoice_no']]);
        $pay_success = 'Payment recorded. Thank you!';
        // refresh invoice
        $stmt->execute([$job]);
        $inv = $stmt->fetch();
    }
}

include 'includes/header.php';
?>
<div class="form-panel" style="max-width:800px; margin: 0 auto;">
    <div class="panel-title"><div><h2>Invoice <?= htmlspecialchars($inv['invoice_no']) ?></h2><small>Job <?= htmlspecialchars($inv['job_card_id']) ?></small></div></div>
    <?php if ($pay_error): ?><div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($pay_error) ?></span></div><?php endif; ?>
    <?php if ($pay_success): ?><div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= htmlspecialchars($pay_success) ?></span></div><?php endif; ?>
    <div style="display:flex; gap:18px; align-items:center; justify-content:space-between; margin-top:12px;">
        <div>
            <strong>Customer:</strong> <?= htmlspecialchars($jobrow['customer_name']) ?><br>
            <strong>Vehicle:</strong> <?= htmlspecialchars($jobrow['brand'] . ' ' . $jobrow['model']) ?> (<?= htmlspecialchars($jobrow['vehicle_no']) ?>)
        </div>
        <div style="text-align:right; color: var(--muted);">Created: <?= htmlspecialchars(date('d M Y', strtotime($inv['created_at']))) ?></div>
    </div>

    <table class="service-table" style="margin-top:20px; width:100%;">
        <thead><tr><th>Item</th><th style="text-align:right;">Amount (₹)</th></tr></thead>
        <tbody>
            <tr><td>Service Charges</td><td style="text-align:right;"><?= htmlspecialchars(number_format($inv['service_charges'],2)) ?></td></tr>
            <tr><td>Parts Charges</td><td style="text-align:right;"><?= htmlspecialchars(number_format($inv['parts_charges'],2)) ?></td></tr>
            <tr><td>CGST</td><td style="text-align:right;"><?= htmlspecialchars(number_format($inv['cgst'],2)) ?></td></tr>
            <tr><td>SGST</td><td style="text-align:right;"><?= htmlspecialchars(number_format($inv['sgst'],2)) ?></td></tr>
            <tr style="font-weight:800;"><td>Total Payable</td><td style="text-align:right;"><?= htmlspecialchars(number_format($inv['total_payable'],2)) ?></td></tr>
        </tbody>
    </table>

    <div style="margin-top:18px; display:flex; gap:12px;">
        <a href="invoices_list.php" class="btn-secondary">Back</a>
        <?php if ($userRole === 'CUSTOMER' && $inv['payment_status'] === 'UNPAID'): ?>
            <form method="POST" style="display:inline-flex; gap:8px; align-items:center;">
                <input type="hidden" name="action" value="pay_invoice">
                <select name="payment_method" class="form-select" style="width:160px;">
                    <option value="UPI">UPI</option>
                    <option value="CASH">Cash</option>
                    <option value="CARD">Card</option>
                </select>
                <button class="btn-primary" type="submit">Pay Now</button>
            </form>
        <?php endif; ?>
        <a class="btn-secondary" href="invoice_pdf.php?job=<?= urlencode($job) ?>" target="_blank">Download PDF</a>
        <button class="btn-secondary" onclick="window.print();">Print / Save PDF</button>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
ensureLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

try {
    if ($userRole === 'ADMIN') {
        $stmt = $pdo->query("SELECT i.*, j.vehicle_no, j.job_card_id, u.name AS customer_name FROM invoices i JOIN job_cards j ON i.job_card_id = j.job_card_id JOIN vehicles v ON j.vehicle_no = v.vehicle_no JOIN users u ON v.customer_id = u.id ORDER BY i.created_at DESC");
        $rows = $stmt->fetchAll();
    } elseif ($userRole === 'MECHANIC') {
        $stmt = $pdo->prepare("SELECT i.*, j.vehicle_no, j.job_card_id, u.name AS customer_name FROM invoices i JOIN job_cards j ON i.job_card_id = j.job_card_id JOIN vehicles v ON j.vehicle_no = v.vehicle_no JOIN users u ON v.customer_id = u.id WHERE j.mechanic_id = ? ORDER BY i.created_at DESC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    } else {
        // customer
        $stmt = $pdo->prepare("SELECT i.*, j.vehicle_no, j.job_card_id FROM invoices i JOIN job_cards j ON i.job_card_id = j.job_card_id JOIN vehicles v ON j.vehicle_no = v.vehicle_no WHERE v.customer_id = ? ORDER BY i.created_at DESC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die('DB error: ' . $e->getMessage());
}

include 'includes/header.php';
?>
<div class="panel-card">
    <div class="panel-title"><div><h2>Your Invoices</h2><small>Invoice history and payment status</small></div></div>
    <div class="table-responsive">
        <table class="service-table">
            <thead><tr><th>Invoice</th><th>Job</th><th>Vehicle</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="7" style="color: var(--muted); text-align:center; padding:28px;">No invoices found.</td></tr><?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['invoice_no']) ?></td>
                            <td><?= htmlspecialchars($r['job_card_id']) ?></td>
                            <td><?= htmlspecialchars($r['vehicle_no']) ?></td>
                            <td style="text-align:right;">₹<?= htmlspecialchars(number_format($r['total_payable'],2)) ?></td>
                            <td><?= htmlspecialchars($r['payment_status']) ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($r['created_at']))) ?></td>
                            <td><a class="btn-secondary" href="invoices.php?job=<?= urlencode($r['job_card_id']) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

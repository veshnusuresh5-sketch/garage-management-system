<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
checkRole('ADMIN');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $code = trim($_POST['part_code']);
            $name = trim($_POST['part_name']);
            $qty = intval($_POST['quantity']);
            $price = floatval($_POST['price_per_unit']);
            $low = intval($_POST['low_stock_threshold']);
            if ($code === '' || $name === '') throw new Exception('Code and name are required');
            $stmt = $pdo->prepare('INSERT INTO inventory (part_code, part_name, quantity, price_per_unit, low_stock_threshold) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$code, $name, $qty, $price, $low]);
            $message = 'Part added.';
        } elseif ($_POST['action'] === 'delete') {
            $code = $_POST['part_code'];
            $pdo->prepare('DELETE FROM inventory WHERE part_code = ?')->execute([$code]);
            $message = 'Part removed.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$items = $pdo->query('SELECT * FROM inventory ORDER BY part_name ASC')->fetchAll();
include 'includes/header.php';
?>
<div class="panel-card">
    <div class="panel-title"><div><h2>Inventory Manager</h2><small>Add or remove parts</small></div></div>
    <?php if ($message): ?><div class="alert-box alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr 120px 140px; gap:12px; align-items:center; margin-bottom:16px;">
        <input type="hidden" name="action" value="add">
        <input type="text" name="part_code" placeholder="Part Code (unique)" class="form-control" required>
        <input type="text" name="part_name" placeholder="Part name" class="form-control" required>
        <input type="number" name="quantity" class="form-control" placeholder="Quantity" value="0">
        <input type="text" name="price_per_unit" class="form-control" placeholder="Price per unit" value="0">
        <input type="number" name="low_stock_threshold" class="form-control" placeholder="Low stock threshold" value="5">
        <div style="grid-column: 1 / -1; text-align:right;"><button type="submit" class="btn-primary">Add Part</button></div>
    </form>

    <div class="table-responsive">
        <table class="inventory-table">
            <thead><tr><th>Code</th><th>Name</th><th>Qty</th><th>Price</th><th>Low</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($items)): ?><tr><td colspan="6" style="text-align:center; color:var(--muted);">No parts</td></tr><?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['part_code']) ?></td>
                            <td><?= htmlspecialchars($it['part_name']) ?></td>
                            <td><?= htmlspecialchars($it['quantity']) ?></td>
                            <td>₹<?= number_format($it['price_per_unit'],2) ?></td>
                            <td><?= htmlspecialchars($it['low_stock_threshold']) ?></td>
                            <td>
                                <form method="POST" style="margin:0; display:inline-block;"><input type="hidden" name="action" value="delete"><input type="hidden" name="part_code" value="<?= htmlspecialchars($it['part_code']) ?>"><button type="submit" class="btn-secondary">Delete</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

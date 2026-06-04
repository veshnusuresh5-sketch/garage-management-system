<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
checkRole('ADMIN');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_stock') {
        $part_code = trim($_POST['part_code'] ?? '');
        $new_qty = intval($_POST['quantity'] ?? 0);
        $new_price = floatval($_POST['price_per_unit'] ?? 0.0);

        if ($part_code !== '' && $new_qty >= 0 && $new_price >= 0) {
            $stmt = $pdo->prepare('UPDATE inventory SET quantity = ?, price_per_unit = ? WHERE part_code = ?');
            $stmt->execute([$new_qty, $new_price, $part_code]);
            $message = 'Inventory updated successfully.';
        } else {
            $error = 'Enter valid quantity and pricing values.';
        }
    }
    if ($_POST['action'] === 'add_part') {
        $part_code = trim($_POST['part_code'] ?? '');
        $part_name = trim($_POST['part_name'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        $price = floatval($_POST['price_per_unit'] ?? 0.0);
        $low_stock = intval($_POST['low_stock_threshold'] ?? 5);

        if ($part_code !== '' && $part_name !== '' && $quantity >= 0 && $price >= 0 && $low_stock >= 0) {
            $stmt = $pdo->prepare('INSERT INTO inventory (part_code, part_name, quantity, price_per_unit, low_stock_threshold) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), price_per_unit = VALUES(price_per_unit), low_stock_threshold = VALUES(low_stock_threshold)');
            $stmt->execute([$part_code, $part_name, $quantity, $price, $low_stock]);
            $message = 'Part record saved successfully.';
        } else {
            $error = 'Provide valid values for all part fields.';
        }
    }
    if ($_POST['action'] === 'seed_dummy') {
        $items = [
            ['BRK-001', 'Premium Brake Pad Set', 24, 1999.00, 4],
            ['OIL-001', 'Synthetic Engine Oil 5W-40', 46, 749.00, 7],
            ['FLT-001', 'High-Flow Air Filter', 33, 650.00, 5],
            ['SPK-001', 'Performance Spark Plug Set', 28, 1299.00, 6],
            ['BAT-001', 'Car Battery Elite 55Ah', 18, 5499.00, 3],
            ['BELT-001', 'Timing Belt (Car)', 16, 2199.00, 4],
            ['SHK-001', 'Shock Absorber (Front)', 12, 4250.00, 3],
            ['RAD-001', 'Radiator Hose Set', 20, 1299.00, 5],
            ['WD-001', 'Windshield Wiper Set', 38, 449.00, 8],
            ['FAN-001', 'Cooling Fan Assembly', 12, 3899.00, 3],
        ];
        $stmt = $pdo->prepare('INSERT INTO inventory (part_code, part_name, quantity, price_per_unit, low_stock_threshold) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), price_per_unit = VALUES(price_per_unit), low_stock_threshold = VALUES(low_stock_threshold)');
        foreach ($items as $item) {
            $stmt->execute($item);
        }
        $message = 'Inventory seeded with common car parts.';
    }
}

$parts = $pdo->query('SELECT * FROM inventory ORDER BY part_code ASC')->fetchAll();

include 'includes/header.php';
?>
<div class="panel-card" style="margin-bottom: 28px;">
    <div class="panel-title">
        <div>
            <h2>Parts Inventory</h2>
            <small>Manage car garage stock and status</small>
        </div>
        <div class="nav-actions">
            <a class="btn-secondary" href="admin_dash.php"><i class='bx bx-left-arrow'></i> Back</a>
            <a class="btn-secondary" href="logout.php"><i class='bx bx-log-out-circle'></i> Sign out</a>
        </div>
    </div>
    <?php if ($message): ?><div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= htmlspecialchars($message) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
</div>

<div class="portal-grid" style="grid-template-columns: 1.1fr 0.9fr; gap: 28px; margin-bottom: 32px;">
    <div class="form-panel">
        <div class="panel-title"><div><h3>Register Car Part</h3><small>Add or update spare inventory</small></div></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_part">
            <div class="form-row" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                <div class="form-group"><label>Part code</label><input class="form-control" type="text" name="part_code" required placeholder="BRK-001"></div>
                <div class="form-group"><label>Part name</label><input class="form-control" type="text" name="part_name" required placeholder="Brake pad set"></div>
            </div>
            <div class="form-row" style="grid-template-columns: repeat(3, minmax(0,1fr));">
                <div class="form-group"><label>Quantity</label><input class="form-control" type="number" name="quantity" min="0" required value="0"></div>
                <div class="form-group"><label>Price</label><input class="form-control" type="number" step="0.01" name="price_per_unit" min="0" required value="0.00"></div>
                <div class="form-group"><label>Low stock threshold</label><input class="form-control" type="number" name="low_stock_threshold" min="0" required value="5"></div>
            </div>
            <button type="submit" class="btn-primary"><i class='bx bx-save'></i> Save Part</button>
        </form>
    </div>
    <div class="form-panel">
        <div class="panel-title"><div><h3>Seed Car Parts</h3><small>Populate the inventory with sample car items</small></div></div>
        <form method="POST"><input type="hidden" name="action" value="seed_dummy"><button type="submit" class="btn-secondary"><i class='bx bx-bolt'></i> Seed Sample Stock</button></form>
    </div>
</div>

<div class="panel-card">
    <div class="panel-title"><div><h3>Inventory list</h3><small>Active car garage spare parts</small></div></div>
    <div class="table-responsive">
        <table class="inventory-table">
            <thead><tr><th>Code</th><th>Part</th><th>Qty</th><th>Status</th><th>Update</th></tr></thead>
            <tbody>
                <?php if (empty($parts)): ?><tr><td colspan="5" style="color: var(--muted); text-align: center; padding: 28px;">No inventory records yet.</td></tr><?php else: ?>
                    <?php foreach ($parts as $item): $low = $item['quantity'] <= $item['low_stock_threshold']; ?>
                        <tr>
                            <td><?= htmlspecialchars($item['part_code']) ?></td>
                            <td><?= htmlspecialchars($item['part_name']) ?></td>
                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                            <td><span class="status-pill <?= $low ? 'stock-low' : 'stock-ok' ?>"><?= $low ? 'Low stock' : 'In stock' ?></span></td>
                            <td>
                                <form method="POST" style="display: grid; gap: 10px; align-items:center;">
                                    <input type="hidden" name="action" value="update_stock">
                                    <input type="hidden" name="part_code" value="<?= htmlspecialchars($item['part_code']) ?>">
                                    <div style="display: grid; grid-template-columns: repeat(2, minmax(80px,1fr)); gap: 10px;">
                                        <input class="form-control" type="number" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="0">
                                        <input class="form-control" type="number" step="0.01" name="price_per_unit" value="<?= htmlspecialchars($item['price_per_unit']) ?>" min="0">
                                    </div>
                                    <button type="submit" class="btn-secondary" style="align-self:flex-end;">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

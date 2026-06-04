<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
checkRole('MECHANIC');

$mechanic_id = $_SESSION['user_id'];
$error = '';
$success = '';
$job = $_GET['job'] ?? '';
if (!$job && $_SERVER['REQUEST_METHOD'] !== 'POST') die('Missing job id.');

// Ensure invoices columns exist and seed basic parts if missing. This helps when migration
// hasn't been run yet on fresh clones.
try {
    $colsToCheck = ['service_charges','parts_charges','cgst','sgst','total_payable','payment_method','payment_status','created_at'];
    $missing = [];
    $check = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = ?");
    foreach ($colsToCheck as $c) {
        $check->execute([$c]);
        if (!$check->fetch()) $missing[] = $c;
    }
    if (!empty($missing)) {
        // Run same safe migration as migrate_invoices.php
        $stmts = [
            "ALTER TABLE invoices ADD COLUMN service_charges DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER job_card_id",
            "ALTER TABLE invoices ADD COLUMN parts_charges DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER service_charges",
            "ALTER TABLE invoices ADD COLUMN cgst DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER parts_charges",
            "ALTER TABLE invoices ADD COLUMN sgst DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cgst",
            "ALTER TABLE invoices ADD COLUMN total_payable DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER sgst",
            "ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(40) DEFAULT 'UPI' AFTER total_payable",
            "ALTER TABLE invoices ADD COLUMN payment_status ENUM('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID' AFTER payment_method",
            "ALTER TABLE invoices ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_status",
        ];
        foreach ($stmts as $s) {
            try { $pdo->exec($s); } catch (Exception $ex) { /* ignore individual failures */ }
        }
        // seed basic parts
        $parts = [
            ['BRK-001','Premium Brake Pad Set', 50, 1999.00, 4],
            ['OIL-001','Synthetic Engine Oil 5W-40 (1L)', 120, 749.00, 10],
            ['FLT-001','Engine Oil Filter', 80, 299.00, 8],
            ['SPK-001','Spark Plug (Iridium)', 200, 349.00, 20],
            ['BAT-001','12V Car Battery (SMF)', 24, 4999.00, 2],
            ['WPR-001','Wiper Blade Set', 100, 399.00, 8],
            ['TYR-001','Alloy Tyre 17"', 40, 6999.00, 4],
            ['AIR-001','Air Filter', 90, 449.00, 8],
            ['BELT-001','Timing Belt', 30, 2499.00, 3],
            ['CLT-001','Clutch Kit', 20, 6999.00, 2],
        ];
        $ins = $pdo->prepare('INSERT IGNORE INTO inventory (part_code, part_name, quantity, price_per_unit, low_stock_threshold) VALUES (?, ?, ?, ?, ?)');
        foreach ($parts as $p) { try { $ins->execute($p); } catch (Exception $ex) { /* ignore */ } }
    }
} catch (Exception $e) {
    // Non-fatal: show a message on page
    $error = 'Migration check warning: ' . $e->getMessage();
}

// On POST, process creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job = trim($_POST['job_card_id'] ?? '');
    $service_choice = trim($_POST['service_choice'] ?? '');
    $extra_charges = floatval($_POST['extra_charges'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'UPI';
    $parts_codes = $_POST['parts_code'] ?? [];
    $parts_qtys = $_POST['parts_qty'] ?? [];
    $parts_unit = $_POST['parts_unit_price'] ?? [];
    try {
        if ($job === '') throw new Exception('Invalid job id.');
        $v = $pdo->prepare('SELECT mechanic_id, status FROM job_cards WHERE job_card_id = ?');
        $v->execute([$job]);
        $row = $v->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('Job not found.');
        if (intval($row['mechanic_id']) !== intval($mechanic_id)) throw new Exception('You are not authorised to invoice this job.');
        if ($row['status'] !== 'COMPLETED') throw new Exception('Job must be marked COMPLETED before invoicing.');
        $check_inv = $pdo->prepare('SELECT invoice_no FROM invoices WHERE job_card_id = ?');
        $check_inv->execute([$job]);
        if ($check_inv->fetch()) throw new Exception('Invoice already exists for this job.');

        $packages = [
            'Standard Service' => 1500.00,
            'Full Performance Service' => 4999.00,
            'Engine Tune-Up' => 2999.00,
            'Brake Service' => 1999.00,
        ];
        $base_service = isset($packages[$service_choice]) ? $packages[$service_choice] : 0.00;
        $service_charges = $base_service + $extra_charges;

        $parts_total = 0.0;
        $parts_to_insert = [];
        for ($i = 0; $i < count($parts_codes); $i++) {
            $code = trim($parts_codes[$i] ?? '');
            $qty = intval($parts_qtys[$i] ?? 0);
            $unit = floatval($parts_unit[$i] ?? 0);
            if ($code === '' || $qty <= 0) continue;
            $line = $qty * $unit;
            $parts_total += $line;
            $parts_to_insert[] = ['code' => $code, 'qty' => $qty, 'unit' => $unit, 'line' => $line];
        }

        $calc = calculateIndianGST($service_charges, $parts_total);

        $pdo->beginTransaction();
        // generate a unique invoice_no
        $invoice_no = 'INV-' . time() . '-' . strtoupper(substr(md5(uniqid((string)rand(), true)),0,8));
        $ins = $pdo->prepare('INSERT INTO invoices (invoice_no, job_card_id, service_charges, parts_charges, cgst, sgst, total_payable, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$invoice_no, $job, $service_charges, $parts_total, $calc['cgst'], $calc['sgst'], $calc['total'], $payment_method, 'UNPAID']);

        $part_ins = $pdo->prepare('INSERT INTO job_parts_used (job_card_id, part_code, quantity_used) VALUES (?, ?, ?)');
        $part_upd = $pdo->prepare('UPDATE inventory SET quantity = GREATEST(quantity - ?, 0) WHERE part_code = ?');
        foreach ($parts_to_insert as $pi) {
            $part_ins->execute([$job, $pi['code'], $pi['qty']]);
            $part_upd->execute([$pi['qty'], $pi['code']]);
        }

        $pdo->commit();
        $success = 'Invoice created successfully.';
        header('Location: invoices.php?job=' . urlencode($job));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch job and inventory for display
$job_stmt = $pdo->prepare('SELECT j.*, v.brand, v.model, v.vehicle_no, v.customer_id, u.name AS customer_name FROM job_cards j JOIN vehicles v ON j.vehicle_no = v.vehicle_no JOIN users u ON v.customer_id = u.id WHERE j.job_card_id = ? LIMIT 1');
$job_stmt->execute([$job]);
$jobrow = $job_stmt->fetch();
if (!$jobrow) die('Job not found.');

$inv_stmt = $pdo->query('SELECT part_code, part_name, quantity, price_per_unit FROM inventory ORDER BY part_name ASC');
$inventory_items = $inv_stmt->fetchAll();

include 'includes/header.php';
?>
<div class="panel-card">
    <div class="panel-title"><div><h2>Generate Invoice for <?= htmlspecialchars($job) ?></h2><small>Customer: <?= htmlspecialchars($jobrow['customer_name']) ?> — <?= htmlspecialchars($jobrow['brand'] . ' ' . $jobrow['model']) ?></small></div></div>
    <?php if ($error): ?><div class="alert-box alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" style="max-width:860px;">
        <input type="hidden" name="job_card_id" value="<?= htmlspecialchars($job) ?>">
        <div style="display:flex; gap:8px; margin-bottom:8px;">
            <select name="service_choice" class="inline-control" style="flex:1;">
                <option value="Standard Service">Standard Service — ₹1,500</option>
                <option value="Full Performance Service">Full Performance Service — ₹4,999</option>
                <option value="Engine Tune-Up">Engine Tune-Up — ₹2,999</option>
                <option value="Brake Service">Brake Service — ₹1,999</option>
            </select>
            <input type="number" min="0" step="0.01" name="extra_charges" class="inline-control" placeholder="Labour / adjustment fees (₹)" style="width:180px;">
            <select name="payment_method" class="inline-control" style="width:160px;"><option value="UPI">UPI</option><option value="CASH">Cash</option><option value="CARD">Card</option><option value="NET_BANKING">Net Banking</option></select>
        </div>

        <div style="margin-bottom:8px;">
            <label style="font-size:0.85rem; color:var(--muted); font-weight:700; display:block; margin-bottom:6px;">Parts used</label>
            <table style="width:100%; border-collapse: collapse; margin-bottom:8px;">
                <thead><tr style="text-align:left; color:var(--muted); font-size:12px;"><th style="padding:8px;">Part</th><th style="padding:8px; width:120px;">Unit Price (₹)</th><th style="padding:8px; width:120px;">Qty</th><th style="padding:8px; width:80px;"></th></tr></thead>
                <tbody id="partsBody">
                </tbody>
            </table>
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <select id="partsSelect" style="flex:1;" class="inline-control">
                    <option value="">-- Select Part to Add --</option>
                    <?php foreach ($inventory_items as $it): ?>
                        <option data-price="<?= htmlspecialchars($it['price_per_unit']) ?>" value="<?= htmlspecialchars($it['part_code']) ?>"><?= htmlspecialchars($it['part_name'] . ' (' . $it['part_code'] . ') — ₹' . number_format($it['price_per_unit'],2)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-secondary" id="addPartBtn">Add Part</button>
            </div>
            <div style="text-align:right; color:var(--muted);">Parts total: ₹<span id="partsTotal">0.00</span></div>
        </div>

        <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end;"><button type="submit" class="btn-primary">Create Invoice</button></div>
    </form>
</div>

<script>
;(function(){
    const addPartBtn = document.getElementById('addPartBtn');
    const partsSelect = document.getElementById('partsSelect');
    const partsBody = document.getElementById('partsBody');
    const partsTotalEl = document.getElementById('partsTotal');
    if (!addPartBtn || !partsSelect || !partsBody) return;

    function formatMoney(v){ return Number(v).toFixed(2); }
    function recalcPartsTotal(){
        let total = 0;
        partsBody.querySelectorAll('tr').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="parts_qty[]"]').value) || 0;
            const unit = parseFloat(row.querySelector('[name="parts_unit_price[]"]').value) || 0;
            total += qty * unit;
        });
        partsTotalEl.textContent = formatMoney(total);
    }
    window.removePartRow = function(btn){ btn.closest('tr').remove(); recalcPartsTotal(); }

    addPartBtn.addEventListener('click', function(){
        const code = partsSelect.value;
        if (!code) return;
        const option = partsSelect.options[partsSelect.selectedIndex];
        const price = option.getAttribute('data-price') || '0';
        const label = option.textContent;

        const tr = document.createElement('tr');
        tr.innerHTML = '<td style="padding:8px;">'+label+'<input type="hidden" name="parts_code[]" value="'+code+'"></td>'+
                       '<td style="padding:8px; text-align:right;"><input type="hidden" name="parts_unit_price[]" value="'+price+'">₹'+formatMoney(price)+'</td>'+
                       '<td style="padding:8px; text-align:right;"><input type="number" min="1" name="parts_qty[]" value="1" style="width:80px; padding:6px; border-radius:6px; border:1px solid rgba(0,0,0,0.06);"></td>'+
                       '<td style="padding:8px; text-align:center;"><button type="button" class="btn-secondary" onclick="removePartRow(this)">Remove</button></td>';
        partsBody.appendChild(tr);
        tr.querySelector('[name="parts_qty[]"]').addEventListener('input', recalcPartsTotal);
        recalcPartsTotal();
    });
})();
</script>

<?php include 'includes/footer.php'; ?>

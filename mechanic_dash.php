<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
checkRole('MECHANIC');

$mechanic_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle mechanic actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $job_id = trim($_POST['job_card_id'] ?? '');
    try {
        if ($action === 'update_job') {
            $new_status = $_POST['status'] ?? '';
            $notes = trim($_POST['repair_notes'] ?? '');
            // Ensure job belongs to mechanic
            $verify_stmt = $pdo->prepare("SELECT job_card_id FROM job_cards WHERE job_card_id = ? AND mechanic_id = ?");
            $verify_stmt->execute([$job_id, $mechanic_id]);
            if ($verify_stmt->fetch()) {
                $update_stmt = $pdo->prepare("UPDATE job_cards SET status = ?, repair_notes = ? WHERE job_card_id = ?");
                $update_stmt->execute([$new_status, $notes, $job_id]);
                $success_msg = "Job Card " . htmlspecialchars($job_id) . " has been updated successfully!";
            } else {
                $error_msg = 'You are not authorized to update this job.';
            }
        } elseif ($action === 'accept_order') {
            if ($job_id === '') throw new Exception('Invalid order selection.');
            $accept_stmt = $pdo->prepare("UPDATE job_cards SET mechanic_id = ?, status = 'IN_PROGRESS' WHERE job_card_id = ? AND mechanic_id IS NULL AND status = 'PENDING'");
            $accept_stmt->execute([$mechanic_id, $job_id]);
            if ($accept_stmt->rowCount() === 0) throw new Exception('Order accept failed: this order has already been claimed or is unavailable.');
            $success_msg = 'Order ' . htmlspecialchars($job_id) . ' has been accepted and assigned to you.';
        } elseif ($action === 'reject_order') {
            if ($job_id === '') throw new Exception('Invalid order selection.');
            $pdo->exec("CREATE TABLE IF NOT EXISTS job_rejections (id INT AUTO_INCREMENT PRIMARY KEY, job_card_id VARCHAR(128) NOT NULL, mechanic_id INT NOT NULL, rejected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY(job_card_id, mechanic_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $rej = $pdo->prepare("INSERT IGNORE INTO job_rejections (job_card_id, mechanic_id) VALUES (?, ?)");
            $rej->execute([$job_id, $mechanic_id]);
            $success_msg = 'Order ' . htmlspecialchars($job_id) . ' has been rejected and will be available to others.';
        } elseif ($action === 'mark_done') {
            if ($job_id === '') throw new Exception('Invalid order selection.');
            $done_stmt = $pdo->prepare("UPDATE job_cards SET status = 'COMPLETED' WHERE job_card_id = ? AND mechanic_id = ? AND status IN ('PENDING','IN_PROGRESS')");
            $done_stmt->execute([$job_id, $mechanic_id]);
            if ($done_stmt->rowCount() === 0) throw new Exception('Unable to mark work done. The job may already be completed or not assigned to you.');
            $success_msg = 'Work done for ' . htmlspecialchars($job_id) . ' has been recorded.';
        }
    } catch (PDOException $e) {
        $error_msg = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Handle invoice creation with parts and inventory update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invoice') {
    $job_id = trim($_POST['job_card_id'] ?? '');
    $service_choice = trim($_POST['service_choice'] ?? '');
    $extra_charges = floatval($_POST['extra_charges'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'UPI';
    $parts_codes = $_POST['parts_code'] ?? [];
    $parts_qtys = $_POST['parts_qty'] ?? [];
    $parts_unit = $_POST['parts_unit_price'] ?? [];
    try {
        if ($job_id === '') throw new Exception('Invalid job id.');
        $v = $pdo->prepare('SELECT mechanic_id FROM job_cards WHERE job_card_id = ?');
        $v->execute([$job_id]);
        $row = $v->fetch(PDO::FETCH_ASSOC);
        if (!$row || intval($row['mechanic_id']) !== intval($mechanic_id)) throw new Exception('Unauthorized to bill for this job.');
        $check_inv = $pdo->prepare('SELECT invoice_no FROM invoices WHERE job_card_id = ?');
        $check_inv->execute([$job_id]);
        if ($check_inv->fetch()) throw new Exception('Invoice already exists for this job.');

        $packages = [
            'Standard Service' => 1500.00,
            'Full Performance Service' => 4999.00,
            'Engine Tune-Up' => 2999.00,
            'Brake Service' => 1999.00,
        ];
        $base_service = isset($packages[$service_choice]) ? $packages[$service_choice] : 0.00;
        $service_charges = $base_service + $extra_charges;

        // Calculate parts total from submitted items; also prepare inserts
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

        // Insert invoice and parts inside a transaction
        $pdo->beginTransaction();
        // generate unique invoice number
        $invoice_no = 'INV-' . time() . '-' . strtoupper(substr(md5(uniqid((string)rand(), true)),0,8));
        $ins = $pdo->prepare('INSERT INTO invoices (invoice_no, job_card_id, service_charges, parts_charges, cgst, sgst, total_payable, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$invoice_no, $job_id, $service_charges, $parts_total, $calc['cgst'], $calc['sgst'], $calc['total'], $payment_method, 'UNPAID']);

        // insert parts used and decrement inventory
        $part_ins = $pdo->prepare('INSERT INTO job_parts_used (job_card_id, part_code, quantity_used) VALUES (?, ?, ?)');
        $part_upd = $pdo->prepare('UPDATE inventory SET quantity = GREATEST(quantity - ?, 0) WHERE part_code = ?');
        foreach ($parts_to_insert as $pi) {
            $part_ins->execute([$job_id, $pi['code'], $pi['qty']]);
            $part_upd->execute([$pi['qty'], $pi['code']]);
        }

        $pdo->prepare('UPDATE job_cards SET status = ? WHERE job_card_id = ?')->execute(['COMPLETED', $job_id]);
        $pdo->commit();
        $success_msg = 'Invoice generated successfully for ' . htmlspecialchars($job_id) . '.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = $e->getMessage();
    }
}

try {
    $fetch_stmt = $pdo->prepare("SELECT j.*, v.brand, v.model, v.category, v.fuel_type FROM job_cards j JOIN vehicles v ON j.vehicle_no = v.vehicle_no WHERE j.mechanic_id = ? AND j.status != 'COMPLETED' ORDER BY j.created_at DESC");
    $fetch_stmt->execute([$mechanic_id]);
    $my_active_bays = $fetch_stmt->fetchAll();

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_rejections (id INT AUTO_INCREMENT PRIMARY KEY, job_card_id VARCHAR(128) NOT NULL, mechanic_id INT NOT NULL, rejected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY(job_card_id, mechanic_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $open_stmt = $pdo->prepare("SELECT j.job_card_id, j.vehicle_no, j.repair_notes, v.brand, v.model, v.category, v.fuel_type FROM job_cards j JOIN vehicles v ON j.vehicle_no = v.vehicle_no LEFT JOIN job_rejections jr ON jr.job_card_id = j.job_card_id AND jr.mechanic_id = ? WHERE j.mechanic_id IS NULL AND j.status = 'PENDING' AND jr.job_card_id IS NULL ORDER BY j.created_at ASC");
    $open_stmt->execute([$mechanic_id]);
    $open_orders = $open_stmt->fetchAll();

    $all_stmt = $pdo->prepare("SELECT j.job_card_id, j.vehicle_no, j.repair_notes, j.status, j.created_at, j.mechanic_id, u.name AS mechanic_name, v.brand, v.model, v.category, v.fuel_type FROM job_cards j JOIN vehicles v ON j.vehicle_no = v.vehicle_no LEFT JOIN users u ON j.mechanic_id = u.id ORDER BY j.created_at DESC");
    $all_stmt->execute();
    $all_jobs = $all_stmt->fetchAll();
    // Fetch inventory for parts selection
    $inv_stmt = $pdo->query("SELECT part_code, part_name, quantity, price_per_unit FROM inventory ORDER BY part_name ASC");
    $inventory_items = $inv_stmt->fetchAll();
} catch (PDOException $e) {
    die('Data Connection Interruption: ' . $e->getMessage());
}

include 'includes/header.php';
?>

<!-- Details Modal -->
<div id="detailModal" style="display:none; position: fixed; inset:0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--surface-card); border: 1px solid rgba(255,255,255,0.06); padding: 20px; width: 920px; max-width: 96%; border-radius: 12px; color: var(--text-white);">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <h3 id="detailTitle" style="margin:0;">Details</h3>
            <button id="detailClose" style="background:transparent; border: none; color: var(--muted); font-size: 18px; cursor:pointer;">✕</button>
        </div>
        <div id="detailBody" style="margin-top: 12px; max-height: 60vh; overflow: auto; font-size: 14px; color: var(--muted);"></div>
    </div>
</div>

<script>
    async function fetchJson(url) { const res = await fetch(url, { credentials: 'same-origin' }); return res.json(); }
    function showModal(title, html) { document.getElementById('detailTitle').textContent = title; document.getElementById('detailBody').innerHTML = html; document.getElementById('detailModal').style.display = 'flex'; }
    function hideModal() { document.getElementById('detailModal').style.display = 'none'; }
    document.getElementById('detailClose').addEventListener('click', hideModal);
    document.addEventListener('click', function(e){ if (e.target.matches('.view-vehicle')) { e.preventDefault(); const reg = e.target.dataset.reg; fetchJson('api.php?action=vehicle&reg='+encodeURIComponent(reg)).then(data=>{ if (data.error) return showModal('Error', '<div style="color:#ff6b6b;">'+data.error+'</div>'); let v = data.vehicle; let html = '<h4 style="margin:0 0 8px 0;">'+(v.brand||'')+' '+(v.model||'')+' — '+(v.vehicle_no||'')+'</h4>'; html += '<div><strong>Owner:</strong> '+(v.customer_name||'Unknown')+' &nbsp; <strong>Phone:</strong> '+(v.customer_phone||'')+'</div>'; html += '<div style="margin-top:8px;"><strong>Category:</strong> '+(v.category||'')+' &nbsp; <strong>Fuel:</strong> '+(v.fuel_type||'')+'</div>'; if (data.history && data.history.length) { html += '<hr><h4 style="margin:8px 0;">Service History</h4>'; html += '<ul>' + data.history.map(h=>'<li><strong>'+h.job_card_id+'</strong> — '+h.status+' ('+h.created_at+')'+(h.mechanic_name ? ' — '+h.mechanic_name : '')+'<div style="color:var(--muted);">'+(h.repair_notes||'')+'</div></li>').join('') + '</ul>'; } else { html += '<div style="margin-top:8px; color:var(--muted);">No service history found for this vehicle.</div>'; } showModal('Vehicle Details', html); }).catch(err=>showModal('Error', '<div style="color:#ff6b6b;">'+err.message+'</div>')); }});
</script>

<style>
    .mechanic-header h2 { font-size: 2.2rem; font-weight: 800; margin: 0 0 8px 0; letter-spacing: -0.5px; }
    .mechanic-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap: 16px; }
    .glass-card { background: rgba(18,18,18,0.94); border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 22px; box-shadow: 0 20px 52px rgba(0,0,0,0.18); }
</style>

<div class="container" style="padding: 0 10px;">
    <div class="mechanic-header">
        <h2>Service Booking Dashboard</h2>
        <p style="color: var(--muted); margin: 0; max-width: 780px;">Manage service requests, accept priority orders, and keep vehicles moving.</p>
        <div style="display:flex; gap:10px; margin-top:12px;">
            <span style="background: rgba(255,255,255,.06); color: #fff; padding: 8px 14px; border-radius: 999px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,.08);">Live service queue</span>
        </div>
    </div>

    <?php if ($success_msg): ?><div class="alert-box alert-success"><i class='bx bx-check-shield'></i><span><?= htmlspecialchars($success_msg) ?></span></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert-box alert-danger"><i class='bx bx-error-alt'></i><span><?= htmlspecialchars($error_msg) ?></span></div><?php endif; ?>

    <section>
        <?php if (empty($open_orders)): ?>
            <div class="glass-card" style="color: var(--muted);">No open orders are waiting in the queue right now.</div>
        <?php else: ?>
            <div class="mechanic-grid">
                <?php foreach ($open_orders as $order): ?>
                    <div class="glass-card" style="padding:24px;">
                        <div style="display:flex; justify-content:space-between; gap:16px; align-items:center;">
                            <div>
                                <h4 style="margin:0 0 10px 0; font-size:1.1rem; font-weight:700; color:var(--text-white);">Order <?= htmlspecialchars($order['job_card_id']) ?></h4>
                                <div style="font-size:14px; color:var(--muted);">Vehicle: <?= htmlspecialchars($order['brand'] . ' ' . $order['model']) ?> | Plate: <a href="#" class="view-vehicle" data-reg="<?= htmlspecialchars($order['vehicle_no']) ?>" style="color:var(--accent); font-weight:700; text-decoration:none;"><?= htmlspecialchars($order['vehicle_no']) ?></a></div>
                                <div style="margin-top:10px; color: #e5e7eb;">Notes: <?= htmlspecialchars($order['repair_notes']) ?></div>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <form action="" method="POST" style="margin:0;"><input type="hidden" name="action" value="accept_order"><input type="hidden" name="job_card_id" value="<?= htmlspecialchars($order['job_card_id']) ?>"><button type="submit" class="btn-primary" style="min-width:140px;">Accept</button></form>
                                <form action="" method="POST" style="margin:0;"><input type="hidden" name="action" value="reject_order"><input type="hidden" name="job_card_id" value="<?= htmlspecialchars($order['job_card_id']) ?>"><button type="submit" class="btn-secondary" style="min-width:140px;">Reject</button></form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="glass-card" style="margin-top:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <h3 style="font-weight:800; font-size:1.2rem; margin:0;">Service Lifecycle Overview</h3>
            <span style="color:var(--muted);">A complete view of service job progress.</span>
        </div>
        <div style="overflow-x:auto; margin-top:12px;">
            <table style="width:100%; border-collapse: collapse; min-width:840px;">
                <thead><tr style="color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:1.5px; border-bottom:1px solid rgba(255,255,255,.08);"><th style="padding:14px 12px; text-align:left;">Job ID</th><th style="padding:14px 12px; text-align:left;">Vehicle</th><th style="padding:14px 12px; text-align:left;">Notes</th><th style="padding:14px 12px; text-align:left;">Status</th><th style="padding:14px 12px; text-align:left;">Assigned Mechanic</th><th style="padding:14px 12px; text-align:left;">Created</th><th style="padding:14px 12px; text-align:center;">Action</th></tr></thead>
                <tbody>
                    <?php if (empty($all_jobs)): ?><tr><td colspan="7" style="padding:24px; text-align:center; color:var(--muted);">No service jobs are present in the system.</td></tr><?php else: ?>
                        <?php foreach ($all_jobs as $job): ?>
                            <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                                <td style="padding:14px 12px; font-family:monospace; color:var(--text-white);"><?= htmlspecialchars($job['job_card_id']) ?></td>
                                <td style="padding:14px 12px; color:#e5e7eb;"><?= htmlspecialchars($job['brand'] . ' ' . $job['model']) ?> / <a href="#" class="view-vehicle" data-reg="<?= htmlspecialchars($job['vehicle_no']) ?>" style="color:var(--accent); font-weight:700; text-decoration:none;"><?= htmlspecialchars($job['vehicle_no']) ?></a></td>
                                <td style="padding:14px 12px; color:var(--muted); max-width:280px;"><?= htmlspecialchars($job['repair_notes']) ?></td>
                                <td style="padding:14px 12px; color:var(--text-white); text-transform:uppercase;"><span style="padding:6px 12px; border-radius:999px; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);"><?= htmlspecialchars($job['status']) ?></span></td>
                                <td style="padding:14px 12px; color:var(--text-white);"><?= htmlspecialchars($job['mechanic_name'] ?? 'Unassigned') ?></td>
                                <td style="padding:14px 12px; color:var(--muted);"><?= htmlspecialchars(date('d M Y', strtotime($job['created_at']))) ?></td>
                                <td style="padding:14px 12px; text-align:center;">
                                    <?php if ($job['status'] === 'PENDING' && empty($job['mechanic_name'])): ?>
                                        <form action="" method="POST" style="margin:0;"><input type="hidden" name="action" value="accept_order"><input type="hidden" name="job_card_id" value="<?= htmlspecialchars($job['job_card_id']) ?>"><button type="submit" class="btn-primary" style="min-width:130px;">Accept</button></form>
                                    <?php elseif ($job['status'] === 'COMPLETED' && intval($job['mechanic_id']) === intval($mechanic_id)): ?>
                                        <?php $inv_check = $pdo->prepare('SELECT invoice_no FROM invoices WHERE job_card_id = ? LIMIT 1'); $inv_check->execute([$job['job_card_id']]); $hasInv = $inv_check->fetch(); ?>
                                        <?php if ($hasInv): ?>
                                            <a class="btn-secondary" href="invoices.php?job=<?= urlencode($job['job_card_id']) ?>">View Invoice</a>
                                        <?php else: ?>
                                            <a class="btn-primary" href="invoice_create.php?job=<?= urlencode($job['job_card_id']) ?>">Generate Invoice</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--muted); font-size:0.85rem;">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <h3 style="font-weight:800; font-size:1.2rem; margin:18px 0 8px 0;">Active Workshop Bays Assigned</h3>
    <?php if (empty($my_active_bays)): ?>
        <div class="glass-card" style="text-align:center; color:var(--muted); padding: 42px 24px;">All clear! You currently have zero pending vehicles in your active bay checklist.</div>
    <?php else: ?>
        <div class="mechanic-grid">
            <?php foreach ($my_active_bays as $bay): ?>
                <?php $card_status_modifier = 'status-pending'; if ($bay['status'] === 'IN_PROGRESS') $card_status_modifier = 'status-in_progress'; elseif ($bay['status'] === 'COMPLETED') $card_status_modifier = 'status-completed'; ?>
                <div class="glass-card bay-card <?= $card_status_modifier ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:14px;">
                        <h4 style="font-size:1.2rem; font-weight:800; margin:0; display:flex; align-items:center; gap:8px;"><i class='bx bx-wrench' style="color:var(--accent);"></i> Job Ref: <span style="font-family: monospace; color:var(--text-white); background: rgba(255,255,255,0.06); padding:2px 8px; border-radius:6px; border:1px solid rgba(255,255,255,0.08); font-size:0.95rem;"><?= htmlspecialchars($bay['job_card_id']) ?></span></h4>
                        <span style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px;">Diagnostics Active</span>
                    </div>
                    <div class="job-meta-row" style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">
                        <div class="meta-item">Vehicle Model: <strong><?= htmlspecialchars($bay['brand'] . ' ' . $bay['model']) ?></strong></div>
                        <div class="meta-item">Indian Plate: <span class="plate-badge"><?= htmlspecialchars($bay['vehicle_no']) ?></span></div>
                        <div class="meta-item">Class: <strong><?= htmlspecialchars($bay['category']) ?></strong></div>
                        <div class="meta-item">Fuel: <strong><?= htmlspecialchars($bay['fuel_type']) ?></strong></div>
                    </div>
                    <form action="" method="POST" class="update-form-inline" style="margin-top:12px; display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <input type="hidden" name="action" value="update_job">
                        <input type="hidden" name="job_card_id" value="<?= htmlspecialchars($bay['job_card_id']) ?>">
                        <div class="form-group"><label>Advance Operational Stage</label><select name="status" class="inline-control" required><option value="PENDING" <?= $bay['status'] === 'PENDING' ? 'selected' : '' ?>>Stage 1: Awaiting Pit Allocation</option><option value="IN_PROGRESS" <?= $bay['status'] === 'IN_PROGRESS' ? 'selected' : '' ?>>Stage 2: Active Calibration / Tuning</option><option value="COMPLETED" <?= $bay['status'] === 'COMPLETED' ? 'selected' : '' ?>>Stage 3: Diagnostics Completed / Ready</option></select></div>
                        <div class="form-group"><label>Repair Notes & Spare Log</label><input type="text" name="repair_notes" class="inline-control" value="<?= htmlspecialchars($bay['repair_notes'] ?: '') ?>" placeholder="e.g. Swapped brake calipers, flushed gearbox" required></div>
                        <div style="grid-column: 1 / -1; display:flex; gap:12px;"><button type="submit" class="btn-primary" style="height:48px;"><i class='bx bx-check-shield'></i> Save Logs</button>
                        <?php if ($bay['status'] !== 'COMPLETED'): ?>
                            <form action="" method="POST" style="margin:0; display:inline-block;"><input type="hidden" name="action" value="mark_done"><input type="hidden" name="job_card_id" value="<?= htmlspecialchars($bay['job_card_id']) ?>"><button type="submit" class="btn-secondary" style="height:48px; background: #10b981; border-color: transparent;">Mark Work Done</button></form>
                        <?php else: ?>
                            <?php $inv_stmt = $pdo->prepare('SELECT * FROM invoices WHERE job_card_id = ?'); $inv_stmt->execute([$bay['job_card_id']]); $invoice = $inv_stmt->fetch(PDO::FETCH_ASSOC); ?>
                            <?php if ($invoice): ?>
                                <div style="margin-top:16px;"><a href="invoices.php?job=<?= urlencode($bay['job_card_id']) ?>" class="btn-secondary" style="display:inline-block; padding:12px 18px;">View Invoice</a></div>
                            <?php else: ?>
                                <div style="margin-top:12px; grid-column:1 / -1;">
                                    <form action="" method="POST" style="display:block; max-width:760px;">
                                        <input type="hidden" name="action" value="create_invoice">
                                        <input type="hidden" name="job_card_id" value="<?= htmlspecialchars($bay['job_card_id']) ?>">
                                        <label style="font-size:0.85rem; color:var(--muted); font-weight:700; display:block; margin-bottom:8px;">Invoice details</label>
                                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
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

                                        <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end;">
                                            <button type="submit" class="btn-primary" style="height:48px;">Generate Invoice</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?></div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Parts UI handling for invoice creation
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

<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
checkRole('ADMIN');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_mechanic') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($name === '' || $phone === '' || $email === '' || $password === '') {
            $error = 'Please complete all fields to add a mechanic.';
        } else {
            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
            $exists->execute([$email, $phone]);
            if ($exists->fetch()) {
                $error = 'A user already exists with that email or phone.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $phone, $email, $hash, 'MECHANIC']);
                $success = 'Mechanic profile created.';
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'remove_mechanic') {
        $mechanic_id = intval($_POST['mechanic_id'] ?? 0);
        if ($mechanic_id <= 0) {
            $error = 'Invalid mechanic selection.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ?');
            $stmt->execute([$mechanic_id, 'MECHANIC']);
            $success = 'Mechanic removed from the system.';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'change_mechanic_password') {
        $mechanic_id = intval($_POST['mechanic_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($mechanic_id <= 0) {
            $error = 'Invalid mechanic selection.';
        } elseif ($new_password === '' || $confirm_password === '') {
            $error = 'Please enter and confirm the new password.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND role = ?');
            $stmt->execute([$hash, $mechanic_id, 'MECHANIC']);
            $success = 'Mechanic password has been updated successfully.';
        }
    }
}

$total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'CUSTOMER'")->fetchColumn();
$total_mechanics = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'MECHANIC'")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= low_stock_threshold")->fetchColumn();
$monthly_revenue = $pdo->query("SELECT COALESCE(SUM(total_payable), 0) FROM invoices WHERE MONTH(created_at) = MONTH(CURRENT_DATE())")->fetchColumn();

$customer_rows = $pdo->query("SELECT id, name, phone, email, address, created_at FROM users WHERE role = 'CUSTOMER' ORDER BY created_at DESC")->fetchAll();
$mechanic_rows = $pdo->query("SELECT id, name, phone, email, created_at FROM users WHERE role = 'MECHANIC' ORDER BY created_at DESC")->fetchAll();

include 'includes/header.php';
?>
<div class="panel-card" style="margin-bottom: 28px;">
    <div class="panel-title">
        <div>
            <h2>Admin Command Center</h2>
            <small>Control the 911 Garage operations</small>
        </div>
        <div class="nav-actions">
            <a class="btn-secondary" href="inventory.php"><i class='bx bx-package'></i> Inventory</a>
            <a class="btn-secondary" href="job_cards.php"><i class='bx bx-wrench'></i> Job Cards</a>
            <a class="btn-secondary" href="logout.php"><i class='bx bx-log-out-circle'></i> Sign out</a>
        </div>
    </div>
    <?php if ($success): ?><div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
</div>

<div class="track-grid" style="margin-bottom: 32px;">
    <div class="track-card"><div class="label">Customers</div><div class="value"><?= htmlspecialchars($total_customers) ?></div><p style="margin-top: 16px; color: var(--muted);">Registered customer accounts.</p></div>
    <div class="track-card"><div class="label">Mechanics</div><div class="value"><?= htmlspecialchars($total_mechanics) ?></div><p style="margin-top: 16px; color: var(--muted);">Active technician profiles.</p></div>
    <div class="track-card"><div class="label">Low stock items</div><div class="value"><?= htmlspecialchars($low_stock) ?></div><p style="margin-top: 16px; color: var(--muted);">Parts near replenishment threshold.</p></div>
    <div class="track-card"><div class="label">Monthly revenue</div><div class="value"><?= htmlspecialchars(number_format($monthly_revenue, 2)) ?></div><p style="margin-top: 16px; color: var(--muted);">Today's financing snapshot.</p></div>
</div>

<div class="portal-grid" style="grid-template-columns: 1.2fr 0.9fr; gap: 28px; margin-bottom: 32px;">
    <div class="form-panel">
        <div class="panel-title"><div><h3>Commission Mechanic</h3><small>Add a new technician user</small></div></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_mechanic">
            <div class="form-row" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                <div class="form-group"><label>Full name</label><input class="form-control" type="text" name="name" required></div>
                <div class="form-group"><label>Phone</label><input class="form-control" type="text" name="phone" required></div>
            </div>
            <div class="form-row" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required></div>
                <div class="form-group"><label>Password</label><input class="form-control" type="password" name="password" required></div>
            </div>
            <button class="btn-primary" type="submit"><i class='bx bx-user-plus'></i> Add Mechanic</button>
        </form>
    </div>
    <div class="form-panel">
        <div class="panel-title"><div><h3>Mechanic roster</h3><small>Active service team</small></div></div>
        <?php if (empty($mechanic_rows)): ?><p style="color: var(--muted);">No mechanics have been added yet.</p><?php else: ?>
            <div style="display: grid; gap: 14px;">
                <?php foreach ($mechanic_rows as $mech): ?>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 18px; display: flex; justify-content: space-between; align-items: center; gap: 18px;">
                        <div><strong><?= htmlspecialchars($mech['name']) ?></strong><div style="color: var(--muted); font-size: 0.9rem; margin-top: 4px;"><?= htmlspecialchars($mech['email']) ?> · <?= htmlspecialchars($mech['phone']) ?></div></div>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn-secondary" style="padding: 10px 14px;" onclick="openPasswordModal(<?= htmlspecialchars($mech['id']) ?>, '<?= htmlspecialchars($mech['name']) ?>')"><i class='bx bx-key'></i> Password</button>
                            <form method="POST" onsubmit="return confirm('Remove this mechanic?');" style="display: inline;"><input type="hidden" name="action" value="remove_mechanic"><input type="hidden" name="mechanic_id" value="<?= htmlspecialchars($mech['id']) ?>"><button type="submit" class="btn-secondary" style="padding: 10px 16px;">Remove</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel-card">
    <div class="panel-title"><div><h3>Customer directory</h3><small>Customer profiles</small></div></div>
    <div class="table-responsive">
        <table class="customer-table">
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Joined</th></tr></thead>
            <tbody>
                <?php if (empty($customer_rows)): ?><tr><td colspan="6" style="color: var(--muted); text-align: center; padding: 28px;">No customers registered yet.</td></tr><?php else: ?>
                    <?php foreach ($customer_rows as $index => $customer): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($customer['name']) ?></td>
                            <td><?= htmlspecialchars($customer['email']) ?></td>
                            <td><?= htmlspecialchars($customer['phone']) ?></td>
                            <td><?= htmlspecialchars($customer['address'] ?: 'Not provided') ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($customer['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
    <div class="form-panel" style="max-width: 480px; width: 90%;">
        <div class="panel-title">
            <div>
                <h3>Change Mechanic Password</h3>
                <small id="modalMechName">Mechanic Name</small>
            </div>
            <button onclick="closePasswordModal()" style="background: none; border: none; color: var(--text-white); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" id="passwordForm">
            <input type="hidden" name="action" value="change_mechanic_password">
            <input type="hidden" name="mechanic_id" id="modalMechId" value="">
            <div class="form-group">
                <label>New Password</label>
                <input class="form-control" type="password" name="new_password" required placeholder="Min 6 characters">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input class="form-control" type="password" name="confirm_password" required placeholder="Re-enter password">
            </div>
            <div style="display: flex; gap: 12px;">
                <button class="btn-primary" type="submit" style="flex: 1;"><i class='bx bx-check'></i> Update Password</button>
                <button class="btn-secondary" type="button" onclick="closePasswordModal()" style="flex: 1;"><i class='bx bx-x'></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPasswordModal(mechId, mechName) {
    document.getElementById('modalMechId').value = mechId;
    document.getElementById('modalMechName').textContent = mechName;
    document.getElementById('passwordModal').style.display = 'flex';
}
function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('passwordForm').reset();
}
</script>

<?php include 'includes/footer.php'; ?>

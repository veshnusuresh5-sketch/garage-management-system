<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
checkRole('CUSTOMER');

$customerId = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_vehicle') {
        $vehicle_no = validateIndianPlate($_POST['vehicle_no'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $category = $_POST['category'] ?? 'CAR';
        $fuel_type = trim($_POST['fuel_type'] ?? '');

        if (!$vehicle_no || $brand === '' || $model === '' || $fuel_type === '') {
            $error = 'Please fill all vehicle fields correctly.';
        } else {
            $allowed = ['CAR', 'SUV', 'EV', 'HYBRID'];
            if (!in_array($category, $allowed, true)) {
                $error = 'Only car categories are allowed at 911 Garage.';
            } else {
                $exists = $pdo->prepare('SELECT vehicle_no FROM vehicles WHERE vehicle_no = ? LIMIT 1');
                $exists->execute([$vehicle_no]);
                if ($exists->fetch()) {
                    $error = 'This vehicle is already registered in the system.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO vehicles (vehicle_no, customer_id, brand, model, category, fuel_type) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$vehicle_no, $customerId, $brand, $model, $category, $fuel_type]);
                    $success = 'Vehicle added to your 911 Garage virtual fleet.';
                }
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'book_service') {
        $vehicle_no = validateIndianPlate($_POST['vehicle_no'] ?? '');
        $service_type = trim($_POST['service_type'] ?? '');
        $service_notes = trim($_POST['service_notes'] ?? '');
        $preferred_mechanic = intval($_POST['mechanic_id'] ?? 0);

        if (!$vehicle_no || $service_type === '') {
            $error = 'Select a vehicle and service package before booking.';
        } else {
            try {
                $job_card_id = 'JG-' . date('YmdHis') . '-' . random_int(1000, 9999);
                $repair_notes = $service_type;
                if ($service_notes !== '') {
                    $repair_notes .= ' - ' . $service_notes;
                }

                $mechanic_id = null;
                if ($preferred_mechanic > 0) {
                    $mstmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
                    $mstmt->execute([$preferred_mechanic, 'MECHANIC']);
                    if ($mstmt->fetch()) {
                        $mechanic_id = $preferred_mechanic;
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO job_cards (job_card_id, vehicle_no, mechanic_id, repair_notes, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$job_card_id, $vehicle_no, $mechanic_id, $repair_notes, 'PENDING']);
                $success = 'Service request created successfully and queued for the garage team.';
            } catch (PDOException $e) {
                $error = 'Unable to create service request. ' . $e->getMessage();
            }
        }
    }
}

$vehicles = $pdo->prepare('SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC');
$vehicles->execute([$customerId]);
$vehicles = $vehicles->fetchAll();

$mechanics = $pdo->prepare('SELECT id, name FROM users WHERE role = ? ORDER BY name ASC');
$mechanics->execute(['MECHANIC']);
$mechanics = $mechanics->fetchAll();

$requests = $pdo->prepare('SELECT j.job_card_id, j.vehicle_no, j.repair_notes, j.status, j.created_at, u.name AS mechanic_name, i.invoice_no, i.payment_status FROM job_cards j LEFT JOIN users u ON j.mechanic_id = u.id LEFT JOIN invoices i ON j.job_card_id = i.job_card_id WHERE j.vehicle_no IN (SELECT vehicle_no FROM vehicles WHERE customer_id = ?) ORDER BY j.created_at DESC');
$requests->execute([$customerId]);
$requests = $requests->fetchAll();

include 'includes/header.php';
?>
<div class="panel-card" style="margin-bottom: 28px;">
    <div class="panel-title">
        <div>
            <h2>My Garage Portal</h2>
            <small>Register cars and book premium service</small>
        </div>
        <div class="nav-actions">
            <a class="btn-secondary" href="logout.php"><i class='bx bx-log-out-circle'></i> Sign out</a>
        </div>
    </div>
    <?php if ($success): ?>
        <div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= htmlspecialchars($success) ?></span></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
</div>

<div class="portal-grid" style="grid-template-columns: 1.2fr 0.9fr;">
    <div class="form-panel">
        <div class="panel-title">
            <div>
                <h3>Add New Car</h3>
                <small>Register only car categories for 911 Garage</small>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_vehicle">
            <div class="form-row" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="form-group"><label>Registration Number</label><input class="form-control" type="text" name="vehicle_no" required placeholder="KA01AB1234" value="<?= htmlspecialchars($_POST['vehicle_no'] ?? '') ?>"></div>
                <div class="form-group"><label>Manufacturer</label><input class="form-control" type="text" name="brand" required placeholder="Toyota" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>"></div>
            </div>
            <div class="form-row" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="form-group"><label>Model / Trim</label><input class="form-control" type="text" name="model" required placeholder="Camry" value="<?= htmlspecialchars($_POST['model'] ?? '') ?>"></div>
                <div class="form-group"><label>Category</label><select class="form-select" name="category" required><option value="CAR">Sedan / Hatchback</option><option value="SUV">SUV / Crossover</option><option value="EV">Electric Vehicle</option><option value="HYBRID">Hybrid Car</option></select></div>
            </div>
            <div class="form-group"><label>Fuel Type</label><select class="form-select" name="fuel_type" required><option value="PETROL">Petrol</option><option value="DIESEL">Diesel</option><option value="HYBRID">Hybrid</option><option value="ELECTRIC">Electric</option></select></div>
            <button type="submit" class="btn-primary"><i class='bx bx-car'></i> Add Car</button>
        </form>
    </div>
    <div class="form-panel">
        <div class="panel-title">
            <div>
                <h3>Book Service</h3>
                <small>Schedule a premium service assignment</small>
            </div>
        </div>
        <?php if (empty($vehicles)): ?>
            <p style="color: var(--muted);">Register a car first to book a service request.</p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="book_service">
                <div class="form-group"><label>Select vehicle</label><select class="form-select" name="vehicle_no" required><option value="">Choose vehicle</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= htmlspecialchars($vehicle['vehicle_no']) ?>"><?= htmlspecialchars($vehicle['vehicle_no'] . ' — ' . $vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Service package</label><select class="form-select" name="service_type" required><option value="">Choose package</option><option value="Quick Inspection">Quick Inspection</option><option value="Premium Tune-Up">Premium Tune-Up</option><option value="Full Systems Service">Full Systems Service</option><option value="EV Health Check">EV Health Check</option></select></div>
                <div class="form-group"><label>Preferred mechanic (optional)</label><select class="form-select" name="mechanic_id"><option value="">Auto-assign available technician</option><?php foreach ($mechanics as $mech): ?><option value="<?= htmlspecialchars($mech['id']) ?>"><?= htmlspecialchars($mech['name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Technical notes</label><textarea class="form-textarea" name="service_notes" placeholder="Describe service request or symptoms..."></textarea></div>
                <button type="submit" class="btn-primary"><i class='bx bx-check-shield'></i> Submit Service Request</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="panel-card" style="margin-top: 28px;">
    <div class="panel-title">
        <div>
            <h3>Registered Fleet</h3>
            <small>Your registered car garage</small>
        </div>
    </div>
    <?php if (empty($vehicles)): ?>
        <p style="color: var(--muted);">No cars registered yet. Use the form above to add your first vehicle.</p>
    <?php else: ?>
        <div class="vehicles-grid">
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="vehicle-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px;"><div><strong style="font-size: 1.1rem;"><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></strong><div style="color: var(--muted); font-size: 0.9rem;">Category: <?= htmlspecialchars($vehicle['category']) ?></div></div><span class="status-badge status-ok" style="background: rgba(245, 245, 245, 0.08); color: var(--accent);">Car only</span></div>
                    <div style="display: flex; justify-content: space-between; gap: 16px; align-items: center;"><span style="color: var(--muted);">Plate</span><strong><?= htmlspecialchars($vehicle['vehicle_no']) ?></strong></div>
                    <div style="display: flex; justify-content: space-between; gap: 16px; margin-top: 16px; color: var(--muted);"><span>Fuel</span><span><?= htmlspecialchars($vehicle['fuel_type']) ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($requests)): ?>
    <div class="panel-card">
        <div class="panel-title">
            <div>
                <h3>Active requests</h3>
                <small>Service history and open job cards</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="service-table">
                <thead><tr><th>Job Code</th><th>Vehicle</th><th>Service</th><th>Status</th><th>Assigned</th><th>Date</th><th>Invoice</th></tr></thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= htmlspecialchars($request['job_card_id']) ?></td>
                            <td><?= htmlspecialchars($request['vehicle_no']) ?></td>
                            <td><?= htmlspecialchars($request['repair_notes']) ?></td>
                            <td><span class="status-pill status-<?= strtolower(str_replace(' ', '_', $request['status'])) ?>"><i class='bx bx-loader-circle'></i><?= htmlspecialchars($request['status']) ?></span></td>
                            <td><?= htmlspecialchars($request['mechanic_name'] ?: 'Standby') ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($request['created_at']))) ?></td>
                            <td>
                                <?php if (!empty($request['invoice_no'])): ?>
                                    <a class="btn-secondary" href="invoices.php?job=<?= urlencode($request['job_card_id']) ?>">View Invoice</a>
                                    <?php if ($request['payment_status'] === 'UNPAID'): ?>
                                        <a class="btn-primary" href="invoices.php?job=<?= urlencode($request['job_card_id']) ?>">Pay</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--muted);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

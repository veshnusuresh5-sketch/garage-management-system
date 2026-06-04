<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
checkRole('ADMIN');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['job_card_id'])) {
    $job_card_id = trim($_POST['job_card_id']);
    if ($_POST['action'] === 'update_status' && isset($_POST['status'])) {
        $status = $_POST['status'];
        if (in_array($status, ['PENDING', 'IN_PROGRESS', 'COMPLETED'], true)) {
            $stmt = $pdo->prepare('UPDATE job_cards SET status = ? WHERE job_card_id = ?');
            $stmt->execute([$status, $job_card_id]);
            $message = 'Job card status updated.';
        }
    }
}

$jobs = $pdo->query('SELECT j.job_card_id, j.vehicle_no, j.repair_notes, j.status, j.created_at, u.name AS mechanic_name FROM job_cards j LEFT JOIN users u ON j.mechanic_id = u.id ORDER BY j.created_at DESC')->fetchAll();

include 'includes/header.php';
?>
<div class="panel-card" style="margin-bottom: 28px;">
    <div class="panel-title">
        <div>
            <h2>Live Job Cards</h2>
            <small>Track current service orders</small>
        </div>
        <div class="nav-actions">
            <a class="btn-secondary" href="admin_dash.php"><i class='bx bx-left-arrow'></i> Back</a>
            <a class="btn-secondary" href="logout.php"><i class='bx bx-log-out-circle'></i> Sign out</a>
        </div>
    </div>
    <?php if ($message): ?><div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= htmlspecialchars($message) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
</div>

<div class="panel-card">
    <div class="table-responsive">
        <table class="job-table">
            <thead><tr><th>Job</th><th>Vehicle</th><th>Service</th><th>Status</th><th>Mechanic</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($jobs)): ?><tr><td colspan="7" style="color: var(--muted); text-align: center; padding: 28px;">No job cards are active yet.</td></tr><?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?= htmlspecialchars($job['job_card_id']) ?></td>
                            <td><?= htmlspecialchars($job['vehicle_no']) ?></td>
                            <td><?= htmlspecialchars($job['repair_notes']) ?></td>
                            <td><span class="status-pill status-<?= strtolower(str_replace(' ', '_', $job['status'])) ?>"><?= htmlspecialchars($job['status']) ?></span></td>
                            <td><?= htmlspecialchars($job['mechanic_name'] ?: 'Unassigned') ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($job['created_at']))) ?></td>
                            <td>
                                <form method="POST" style="display: grid; gap: 8px;">
                                    <input type="hidden" name="job_card_id" value="<?= htmlspecialchars($job['job_card_id']) ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <select name="status" class="form-select" style="min-width: 160px;">
                                        <option value="PENDING" <?= $job['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                                        <option value="IN_PROGRESS" <?= $job['status'] === 'IN_PROGRESS' ? 'selected' : '' ?>>In progress</option>
                                        <option value="COMPLETED" <?= $job['status'] === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                    <button type="submit" class="btn-secondary">Update</button>
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

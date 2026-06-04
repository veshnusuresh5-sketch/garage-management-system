<?php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $password === '') {
        $error = 'All fields are required to register.';
    } else {
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $exists->execute([$email, $phone]);
        if ($exists->fetch()) {
            $error = 'A customer with that email or phone already exists.';
        } else {
            $pdo->prepare('INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $phone, $email, password_hash($password, PASSWORD_BCRYPT), 'CUSTOMER']);
            $success = 'Registration successful. You may now log in.';
        }
    }
}

include 'includes/header.php';
?>
<div class="form-panel" style="max-width: 620px; margin: 0 auto;">
    <div class="panel-title">
        <div>
            <h2>Register for 911 Garage</h2>
            <small>Sign up as a customer for premium car service.</small>
        </div>
    </div>
    <?php if ($success): ?>
        <div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= htmlspecialchars($success) ?></span></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-row" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="form-group">
                <label>Full name</label>
                <input class="form-control" type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone number</label>
                <input class="form-control" type="text" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Email address</label>
            <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <button type="submit" class="btn-primary"><i class='bx bx-user-plus'></i> Register Account</button>
    </form>
    <p style="margin-top: 16px; color: var(--muted);">Already registered? <a style="color: var(--accent);" href="login.php">Log in here</a>.</p>
</div>
<?php include 'includes/footer.php'; ?>

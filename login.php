<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'ADMIN') {
                header('Location: admin_dash.php');
                exit;
            }
            if ($user['role'] === 'MECHANIC') {
                header('Location: mechanic_dash.php');
                exit;
            }
            header('Location: customer_dash.php');
            exit;
        }
        $error = 'Login failed. Check your credentials and try again.';
    }
}

include 'includes/header.php';
?>
<div class="form-panel" style="max-width: 580px; margin: 0 auto;">
    <div class="panel-title">
        <div>
            <h2>Login to 911 Garage</h2>
            <small>Access customer and admin control.</small>
        </div>
    </div>
    <?php if ($error): ?>
        <div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Email address</label>
            <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn-primary" type="submit"><i class='bx bx-log-in'></i> Sign In</button>
    </form>
    <p style="margin-top: 16px; color: var(--muted);">New to 911 Garage? <a style="color: var(--accent);" href="register.php">Create an account</a>.<br><a style="color: var(--accent);" href="forgot_password.php">Forgot your password?</a></p>
</div>
<?php include 'includes/footer.php'; ?>

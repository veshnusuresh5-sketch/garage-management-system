<?php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if ($token === '') {
    $error = 'Invalid or missing reset token.';
} else {
    $stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Reset token is invalid or has expired. Please request a new one.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        if ($password === '' || $confirm === '') {
            $error = 'Please enter and confirm your new password.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            $success = 'Password has been reset successfully! You can now <a style="color: var(--accent);" href="login.php">login with your new password</a>.';
        }
    }
}

include 'includes/header.php';
?>
<div class="form-panel" style="max-width: 580px; margin: 0 auto;">
    <div class="panel-title">
        <div>
            <h2>Set New Password</h2>
            <small>Create a new password for your account</small>
        </div>
    </div>
    <?php if ($error): ?>
        <div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= $success ?></span></div>
    <?php endif; ?>
    <?php if (!$error && $_SERVER['REQUEST_METHOD'] !== 'POST' && !$success): ?>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input class="form-control" type="password" name="password" required placeholder="Min 6 characters">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input class="form-control" type="password" name="confirm_password" required placeholder="Re-enter your password">
            </div>
            <button class="btn-primary" type="submit"><i class='bx bx-check-circle'></i> Update Password</button>
        </form>
    <?php endif; ?>
    <p style="margin-top: 16px; color: var(--muted);"><a style="color: var(--accent);" href="login.php">Back to login</a></p>
</div>
<?php include 'includes/footer.php'; ?>

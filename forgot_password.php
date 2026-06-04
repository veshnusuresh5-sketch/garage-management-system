<?php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No account found with that email address.';
        } else {
            // Generate reset token (24-hour expiry)
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?');
            $stmt->execute([$token, $expiry, $user['id']]);

            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/car garage/reset_password.php?token=" . urlencode($token);
            $success = "<strong>Password reset link generated!</strong><br><br>Reset Link (valid for 24 hours):<br><code style='background: rgba(255,255,255,0.08); padding: 12px; border-radius: 8px; display: block; word-break: break-all; margin: 12px 0;'>" . htmlspecialchars($reset_link) . "</code><br><small style='color: var(--muted);'>Copy and paste this link in your browser or send to {$user['name']}.</small>";
        }
    }
}

include 'includes/header.php';
?>
<div class="form-panel" style="max-width: 580px; margin: 0 auto;">
    <div class="panel-title">
        <div>
            <h2>Reset Password</h2>
            <small>Request a password reset link</small>
        </div>
    </div>
    <?php if ($error): ?>
        <div class="alert-box alert-danger"><i class='bx bx-error-circle'></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-box alert-success"><i class='bx bx-check-circle'></i><span><?= $success ?></span></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Email address</label>
            <input class="form-control" type="email" name="email" required placeholder="Enter your registered email">
        </div>
        <button class="btn-primary" type="submit"><i class='bx bx-key'></i> Generate Reset Link</button>
    </form>
    <p style="margin-top: 16px; color: var(--muted);">Remember your password? <a style="color: var(--accent);" href="login.php">Back to login</a>.</p>
</div>
<?php include 'includes/footer.php'; ?>

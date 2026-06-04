<?php
require_once 'config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_admin') {
    try {
        $email = 'admin@911garage.local';
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $pdo->exec("DELETE FROM users WHERE email = '$email'");
        $pdo->prepare('INSERT INTO users (name, phone, email, password, role, address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['911 Garage Admin', '9999999999', $email, $hash, 'ADMIN', 'Admin HQ']);
        
        $message = '✓ Admin account reset successfully!<br>Email: <strong>admin@911garage.local</strong><br>Password: <strong>admin123</strong>';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed_db') {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $pdo->exec("DELETE FROM inventory;");
        $pdo->exec("DELETE FROM invoices;");
        $pdo->exec("DELETE FROM job_cards;");
        $pdo->exec("DELETE FROM vehicles;");
        $pdo->exec("DELETE FROM users;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        
        $email = 'admin@911garage.local';
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $pdo->prepare('INSERT INTO users (name, phone, email, password, role, address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['911 Garage Admin', '9999999999', $email, $hash, 'ADMIN', 'Admin HQ']);
        
        $pdo->prepare('INSERT INTO users (name, phone, email, password, role, address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['Rohan Mehta', '9988776655', 'rohan@911garage.local', password_hash('mech123', PASSWORD_BCRYPT), 'MECHANIC', 'Pit Lane']);
        
        $pdo->prepare('INSERT INTO users (name, phone, email, password, role, address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['Simran Kaur', '9876543210', 'simran@example.com', password_hash('user123', PASSWORD_BCRYPT), 'CUSTOMER', 'Mumbai']);
        
        $pdo->prepare('INSERT INTO vehicles (vehicle_no, customer_id, brand, model, category, fuel_type) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['MH01AB1234', 3, 'Toyota', 'Camry', 'CAR', 'PETROL']);
        
        $pdo->prepare('INSERT INTO inventory (part_code, part_name, quantity, price_per_unit, low_stock_threshold) VALUES (?, ?, ?, ?, ?)')
            ->execute(['BRK-001', 'Premium Brake Pad Set', 24, 1999.00, 4]);
        $pdo->prepare('INSERT INTO inventory (part_code, part_name, quantity, price_per_unit, low_stock_threshold) VALUES (?, ?, ?, ?, ?)')
            ->execute(['OIL-001', 'Synthetic Engine Oil 5W-40', 46, 749.00, 7]);
        
        $message = '✓ Database seeded successfully!<br><br><strong>Login Credentials:</strong><br>Admin: admin@911garage.local / admin123<br>Mechanic: rohan@911garage.local / mech123<br>Customer: simran@example.com / user123';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>911 Garage Setup</title>
    <style>
        :root {
            --primary: #D61F26;
            --bg: #111111;
            --surface: #1A1A1A;
            --text: #FFFFFF;
            --muted: rgba(245, 245, 245, 0.72);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .setup-box {
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 40px;
            max-width: 580px;
            width: 100%;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 2rem;
        }
        .subtitle {
            color: var(--muted);
            margin-bottom: 32px;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .alert-success {
            background: rgba(52, 211, 153, 0.12);
            border: 1px solid rgba(52, 211, 153, 0.2);
            color: #9ef0cf;
        }
        .alert-danger {
            background: rgba(255, 77, 77, 0.12);
            border: 1px solid rgba(255, 77, 77, 0.2);
            color: #ffb3b3;
        }
        .button-group {
            display: grid;
            gap: 12px;
        }
        button {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #ff434a);
            color: white;
            box-shadow: 0 8px 24px rgba(214, 31, 38, 0.24);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(214, 31, 38, 0.32);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
        }
        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 18px;
            margin-top: 24px;
            font-size: 0.95rem;
        }
        .info-box strong {
            color: var(--primary);
        }
        a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="setup-box">
        <h1>911 Garage Setup</h1>
        <p class="subtitle">Initialize database and create admin account</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="button-group">
            <form method="POST">
                <input type="hidden" name="action" value="seed_db">
                <button type="submit" class="btn-primary" onclick="return confirm('This will reset all data. Continue?');">
                    🔄 Full Database Reset & Seed
                </button>
            </form>
            
            <form method="POST">
                <input type="hidden" name="action" value="reset_admin">
                <button type="submit" class="btn-secondary">
                    👤 Reset Admin Account Only
                </button>
            </form>
        </div>

        <div class="info-box">
            <strong>After setup, login with:</strong><br>
            📧 Email: <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px;">admin@911garage.local</code><br>
            🔑 Password: <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px;">admin123</code><br><br>
            <a href="login.php">→ Go to Login</a>
        </div>
    </div>
</body>
</html>

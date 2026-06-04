<?php
require_once 'config/db.php';
// Simple migration: ensure invoices table has required columns. Run this once via browser or CLI.
try {
    $needed = [
        'service_charges' => "ALTER TABLE invoices ADD COLUMN service_charges DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER job_card_id",
        'parts_charges' => "ALTER TABLE invoices ADD COLUMN parts_charges DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER service_charges",
        'cgst' => "ALTER TABLE invoices ADD COLUMN cgst DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER parts_charges",
        'sgst' => "ALTER TABLE invoices ADD COLUMN sgst DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cgst",
        'total_payable' => "ALTER TABLE invoices ADD COLUMN total_payable DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER sgst",
        'payment_method' => "ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(40) DEFAULT 'UPI' AFTER total_payable",
        'payment_status' => "ALTER TABLE invoices ADD COLUMN payment_status ENUM('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID' AFTER payment_method",
        'created_at' => "ALTER TABLE invoices ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_status",
    ];

    $res = $pdo->query("SHOW TABLES LIKE 'invoices'")->fetch();
    if (!$res) {
        echo "Invoices table not found. Ensure your config/db.php creates it.\n";
    } else {
        foreach ($needed as $col => $sql) {
            $check = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = ?");
            $check->execute([$col]);
            if (!$check->fetch()) {
                $pdo->exec($sql);
                echo "Added column: $col\n";
            } else {
                echo "Column exists: $col\n";
            }
        }
    }

    // Seed basic car parts if inventory table exists
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
    foreach ($parts as $p) {
        $ins->execute($p);
    }
    echo "Seeded basic parts.\n";
} catch (PDOException $e) {
    echo 'Migration error: ' . $e->getMessage();
}

echo "Done.\n";

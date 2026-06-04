<?php
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'customer') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid customer id']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, name, email, phone, address, created_at FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$id, 'CUSTOMER']);
    $customer = $stmt->fetch();
    if (!$customer) {
        echo json_encode(['error' => 'Customer not found']);
        exit;
    }
    $vehicles = $pdo->prepare('SELECT vehicle_no, brand, model, category, fuel_type FROM vehicles WHERE customer_id = ?');
    $vehicles->execute([$id]);
    $customer['vehicles'] = $vehicles->fetchAll();
    echo json_encode(['customer' => $customer]);
    exit;
}

if ($action === 'vehicle') {
    $reg = trim($_GET['reg'] ?? '');
    if ($reg === '') {
        echo json_encode(['error' => 'Invalid vehicle registration']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT v.vehicle_no, v.brand, v.model, v.category, v.fuel_type, u.name AS customer_name, u.phone AS customer_phone FROM vehicles v JOIN users u ON v.customer_id = u.id WHERE v.vehicle_no = ?');
    $stmt->execute([$reg]);
    $vehicle = $stmt->fetch();
    if (!$vehicle) {
        echo json_encode(['error' => 'Vehicle not found']);
        exit;
    }
    $history = $pdo->prepare('SELECT job_card_id, repair_notes, status, created_at FROM job_cards WHERE vehicle_no = ? ORDER BY created_at DESC');
    $history->execute([$reg]);
    $vehicle['history'] = $history->fetchAll();
    echo json_encode(['vehicle' => $vehicle]);
    exit;
}

echo json_encode(['error' => 'Invalid API action']);

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$plate          = format_plate($_POST['plate'] ?? '');
$brand          = trim($_POST['brand'] ?? '');
$model          = trim($_POST['model'] ?? '');
$year           = (int) ($_POST['year'] ?? 0);
$color          = trim($_POST['color'] ?? '');
$chassisNo      = trim($_POST['chassis_no'] ?? '');
$customerName   = trim($_POST['customer_name'] ?? '');
$customerPhone  = trim($_POST['customer_phone'] ?? '');
$tcVkn          = trim($_POST['tc_vkn'] ?? '');
$insuranceCo    = trim($_POST['insurance_company'] ?? '');
$policyNo       = trim($_POST['policy_no'] ?? '');
$claimNo        = trim($_POST['claim_no'] ?? '');
$note           = trim($_POST['note'] ?? '');

if ($user['role'] === 'workshop' || $user['role'] === 'admin') {
    json_error('Bu rol dosya açamaz', 403);
}

if ($plate === '' || $brand === '' || $model === '' || $customerName === '' || $tcVkn === '') {
    json_error('Zorunlu alanlar eksik');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, customer_id FROM vehicles WHERE plate = ?');
    $stmt->execute([$plate]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        $vehicleId = (int) $vehicle['id'];
    } else {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE tc_vkn = ?');
        $stmt->execute([$tcVkn]);
        $customer = $stmt->fetch();

        if ($customer) {
            $customerId = (int) $customer['id'];
            $stmt = $pdo->prepare('UPDATE customers SET name = ?, phone = ? WHERE id = ?');
            $stmt->execute([$customerName, $customerPhone, $customerId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers (name, phone, tc_vkn) VALUES (?, ?, ?)');
            $stmt->execute([$customerName, $customerPhone, $tcVkn]);
            $customerId = (int) $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare(
            'INSERT INTO vehicles (customer_id, plate, chassis_no, brand, model, year, color) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$customerId, $plate, $chassisNo, $brand, $model, $year ?: null, $color]);
        $vehicleId = (int) $pdo->lastInsertId();
    }

    $fileNumber = generate_file_number($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO damage_files (vehicle_id, advisor_id, file_number, insurance_company, policy_no, claim_no, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$vehicleId, $user['id'], $fileNumber, $insuranceCo, $policyNo, $claimNo, $note]);
    $fileId = (int) $pdo->lastInsertId();

    add_file_log($pdo, $fileId, (int) $user['id'], "Hasar dosyası açıldı ($fileNumber)");

    $pdo->commit();

    json_response(['ok' => true, 'file_id' => $fileId, 'file_number' => $fileNumber]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Dosya oluşturulamadı: ' . $e->getMessage(), 500);
}

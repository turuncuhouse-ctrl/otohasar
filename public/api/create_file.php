<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$plate          = normalize_plate($_POST['plate'] ?? '');
$brand          = trim($_POST['brand'] ?? '');
$model          = trim($_POST['model'] ?? '');
$year           = (int) ($_POST['year'] ?? 0);
$color          = trim($_POST['color'] ?? '');
$chassisNo      = trim($_POST['chassis_no'] ?? '');
$odometerKm     = parse_odometer_km($_POST['odometer_km'] ?? null);
$customerName   = trim($_POST['customer_name'] ?? '');
$customerPhone  = trim($_POST['customer_phone'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');
$tcVkn          = trim($_POST['tc_vkn'] ?? '');
$workOrderNo    = trim($_POST['work_order_no'] ?? '');
$insuranceCo    = trim($_POST['insurance_company'] ?? '');
$policyNo       = trim($_POST['policy_no'] ?? '');
$claimNo        = trim($_POST['claim_no'] ?? '');
$note           = trim($_POST['note'] ?? '');
$damageDate     = parse_damage_date($_POST['damage_date'] ?? null);
$damageTime     = parse_damage_time($_POST['damage_time'] ?? null);
$damageType     = trim($_POST['damage_type'] ?? '');
$damagePlace    = trim($_POST['damage_place'] ?? '');
$vehicleLoc     = parse_vehicle_location($_POST['vehicle_location'] ?? 'serviste') ?? 'serviste';

if ($user['role'] === 'workshop' || $user['role'] === 'admin') {
    json_error('Bu rol dosya açamaz', 403);
}

if ($plate === '' || !is_valid_plate($plate)) {
    json_error('Geçerli plaka giriniz (ör. 35ABC35)');
}
if ($customerName === '' || $customerPhone === '' || $customerAddress === '') {
    json_error('Müşteri adı, telefon ve adres zorunludur');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, customer_id FROM vehicles WHERE REPLACE(UPPER(plate), " ", "") = ?');
    $stmt->execute([$plate]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        $vehicleId = (int) $vehicle['id'];
        $stmt = $pdo->prepare('SELECT customer_id FROM vehicles WHERE id = ?');
        $stmt->execute([$vehicleId]);
        $customerId = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?');
        $stmt->execute([$customerName, $customerPhone, $customerAddress, $customerId]);
        if ($brand !== '' || $model !== '' || $year || $color !== '' || $chassisNo !== '' || $odometerKm !== null) {
            $stmt = $pdo->prepare(
                'UPDATE vehicles SET brand = COALESCE(NULLIF(?, ""), brand), model = COALESCE(NULLIF(?, ""), model),
                 year = COALESCE(?, year), color = COALESCE(NULLIF(?, ""), color), chassis_no = COALESCE(NULLIF(?, ""), chassis_no),
                 odometer_km = COALESCE(?, odometer_km) WHERE id = ?'
            );
            $stmt->execute([$brand, $model, $year ?: null, $color, $chassisNo, $odometerKm, $vehicleId]);
        }
    } else {
        $customerId = null;
        if ($tcVkn !== '') {
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE tc_vkn = ?');
            $stmt->execute([$tcVkn]);
            $row = $stmt->fetch();
            if ($row) {
                $customerId = (int) $row['id'];
            }
        }
        if ($customerId) {
            $stmt = $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?');
            $stmt->execute([$customerName, $customerPhone, $customerAddress, $customerId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers (name, phone, address, tc_vkn) VALUES (?, ?, ?, ?)');
            $stmt->execute([$customerName, $customerPhone, $customerAddress, $tcVkn !== '' ? $tcVkn : 'BELIRSIZ-' . bin2hex(random_bytes(4))]);
            $customerId = (int) $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare(
            'INSERT INTO vehicles (customer_id, plate, chassis_no, brand, model, year, color, odometer_km) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $customerId,
            $plate,
            $chassisNo ?: null,
            $brand !== '' ? $brand : '-',
            $model !== '' ? $model : '-',
            $year ?: null,
            $color ?: null,
            $odometerKm,
        ]);
        $vehicleId = (int) $pdo->lastInsertId();
    }

    $fileNumber = generate_file_number($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO damage_files (vehicle_id, advisor_id, file_number, work_order_no, insurance_company, policy_no, claim_no, note,
            damage_date, damage_time, damage_type, damage_place, vehicle_location, status_changed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $vehicleId,
        $user['id'],
        $fileNumber,
        $workOrderNo !== '' ? $workOrderNo : null,
        $insuranceCo !== '' ? $insuranceCo : null,
        $policyNo !== '' ? $policyNo : null,
        $claimNo !== '' ? $claimNo : null,
        $note !== '' ? $note : null,
        $damageDate,
        $damageTime,
        $damageType !== '' ? mb_substr($damageType, 0, 120) : null,
        $damagePlace !== '' ? mb_substr($damagePlace, 0, 255) : null,
        $vehicleLoc,
    ]);
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

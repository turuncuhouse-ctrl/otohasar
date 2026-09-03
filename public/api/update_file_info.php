<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);

$customerName    = trim($_POST['customer_name'] ?? '');
$customerPhone   = trim($_POST['customer_phone'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');
$customerEmail   = trim($_POST['customer_email'] ?? '');
$tcVkn           = trim($_POST['tc_vkn'] ?? '');

$plate     = normalize_plate($_POST['plate'] ?? '');
$brand     = trim($_POST['brand'] ?? '');
$model     = trim($_POST['model'] ?? '');
$year      = (int) ($_POST['year'] ?? 0);
$color     = trim($_POST['color'] ?? '');
$chassisNo = trim($_POST['chassis_no'] ?? '');
$odometerKm = parse_odometer_km($_POST['odometer_km'] ?? null);

$workOrderNo = trim($_POST['work_order_no'] ?? '');
$insuranceCo = trim($_POST['insurance_company'] ?? '');
$policyNo    = trim($_POST['policy_no'] ?? '');
$claimNo     = trim($_POST['claim_no'] ?? '');
$note        = trim($_POST['note'] ?? '');
$damageDate  = parse_damage_date($_POST['damage_date'] ?? null);
$damageTime  = parse_damage_time($_POST['damage_time'] ?? null);
$damageType  = trim($_POST['damage_type'] ?? '');
$damagePlace = trim($_POST['damage_place'] ?? '');
$vehicleLoc  = parse_vehicle_location($_POST['vehicle_location'] ?? null);

if ($customerName === '' || $customerPhone === '' || $customerAddress === '') {
    json_error('Müşteri adı, telefon ve adres zorunludur');
}
if ($plate === '' || !is_valid_plate($plate)) {
    json_error('Geçerli plaka giriniz (ör. 35ABC35)');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.id AS vehicle_id, v.customer_id, v.plate AS old_plate,
            c.name AS old_customer_name, c.phone AS old_phone, c.tc_vkn AS old_tc_vkn
     FROM damage_files df
     JOIN vehicles v ON v.id = df.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     WHERE df.id = ?'
);
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    json_error('Dosya bulunamadı', 404);
}

if (!can_access_file($user, $file)) {
    json_error('Bu dosyaya erişim yetkiniz yok', 403);
}

$perms = get_file_permissions($user, $file);
if (empty($perms['can_edit'])) {
    json_error('Bilgi güncelleme yetkiniz yok', 403);
}

$customerId = (int) $file['customer_id'];
$vehicleId  = (int) $file['vehicle_id'];

if ($tcVkn !== '' && $tcVkn !== (string) $file['old_tc_vkn']) {
    $chk = $pdo->prepare('SELECT id FROM customers WHERE tc_vkn = ? AND id <> ?');
    $chk->execute([$tcVkn, $customerId]);
    if ($chk->fetch()) {
        json_error('Bu TC/VKN başka bir müşteriye kayıtlı');
    }
}

if ($plate !== normalize_plate((string) $file['old_plate'])) {
    $chk = $pdo->prepare('SELECT id FROM vehicles WHERE REPLACE(UPPER(plate), " ", "") = ? AND id <> ?');
    $chk->execute([$plate, $vehicleId]);
    if ($chk->fetch()) {
        json_error('Bu plaka başka bir araca kayıtlı');
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE customers SET name = ?, phone = ?, address = ?, email = ?, tc_vkn = ? WHERE id = ?'
    );
    $stmt->execute([
        $customerName,
        $customerPhone,
        $customerAddress,
        $customerEmail !== '' ? $customerEmail : null,
        $tcVkn !== '' ? $tcVkn : $file['old_tc_vkn'],
        $customerId,
    ]);

    $stmt = $pdo->prepare(
        'UPDATE vehicles SET plate = ?, brand = ?, model = ?, year = ?, color = ?, chassis_no = ?, odometer_km = ? WHERE id = ?'
    );
    $stmt->execute([
        $plate,
        $brand !== '' ? $brand : '-',
        $model !== '' ? $model : '-',
        $year > 0 ? $year : null,
        $color !== '' ? $color : null,
        $chassisNo !== '' ? $chassisNo : null,
        $odometerKm,
        $vehicleId,
    ]);

    $stmt = $pdo->prepare(
        'UPDATE damage_files
         SET work_order_no = ?, insurance_company = ?, policy_no = ?, claim_no = ?, note = ?,
             damage_date = ?, damage_time = ?, damage_type = ?, damage_place = ?, vehicle_location = ?
         WHERE id = ?'
    );
    $stmt->execute([
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
        $fileId,
    ]);

    add_file_log($pdo, $fileId, (int) $user['id'], 'Dosya bilgileri güncellendi');

    $pdo->commit();

    json_response(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Güncelleme başarısız');
}

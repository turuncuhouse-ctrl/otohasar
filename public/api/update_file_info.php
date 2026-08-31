<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);

$customerName  = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$tcVkn         = trim($_POST['tc_vkn'] ?? '');

$plate     = format_plate($_POST['plate'] ?? '');
$brand     = trim($_POST['brand'] ?? '');
$model     = trim($_POST['model'] ?? '');
$year      = (int) ($_POST['year'] ?? 0);
$color     = trim($_POST['color'] ?? '');
$chassisNo = trim($_POST['chassis_no'] ?? '');

$insuranceCo = trim($_POST['insurance_company'] ?? '');
$policyNo    = trim($_POST['policy_no'] ?? '');
$claimNo     = trim($_POST['claim_no'] ?? '');
$note        = trim($_POST['note'] ?? '');

if ($customerName === '' || $tcVkn === '' || $plate === '' || $brand === '' || $model === '') {
    json_error('Müşteri adı, TC/VKN, plaka, marka ve model zorunludur');
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

if ($tcVkn !== (string) $file['old_tc_vkn']) {
    $chk = $pdo->prepare('SELECT id FROM customers WHERE tc_vkn = ? AND id <> ?');
    $chk->execute([$tcVkn, $customerId]);
    if ($chk->fetch()) {
        json_error('Bu TC/VKN başka bir müşteriye kayıtlı');
    }
}

if ($plate !== format_plate((string) $file['old_plate'])) {
    $chk = $pdo->prepare('SELECT id FROM vehicles WHERE plate = ? AND id <> ?');
    $chk->execute([$plate, $vehicleId]);
    if ($chk->fetch()) {
        json_error('Bu plaka başka bir araca kayıtlı');
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE customers SET name = ?, phone = ?, email = ?, tc_vkn = ? WHERE id = ?'
    );
    $stmt->execute([
        $customerName,
        $customerPhone !== '' ? $customerPhone : null,
        $customerEmail !== '' ? $customerEmail : null,
        $tcVkn,
        $customerId,
    ]);

    $stmt = $pdo->prepare(
        'UPDATE vehicles SET plate = ?, brand = ?, model = ?, year = ?, color = ?, chassis_no = ? WHERE id = ?'
    );
    $stmt->execute([
        $plate,
        $brand,
        $model,
        $year > 0 ? $year : null,
        $color !== '' ? $color : null,
        $chassisNo !== '' ? $chassisNo : null,
        $vehicleId,
    ]);

    $stmt = $pdo->prepare(
        'UPDATE damage_files
         SET insurance_company = ?, policy_no = ?, claim_no = ?, note = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $insuranceCo !== '' ? $insuranceCo : null,
        $policyNo !== '' ? $policyNo : null,
        $claimNo !== '' ? $claimNo : null,
        $note !== '' ? $note : null,
        $fileId,
    ]);

    $changes = [];
    if ($customerName !== (string) $file['old_customer_name']) {
        $changes[] = 'müşteri adı';
    }
    if ($customerPhone !== (string) ($file['old_phone'] ?? '')) {
        $changes[] = 'telefon';
    }
    if ($tcVkn !== (string) $file['old_tc_vkn']) {
        $changes[] = 'TC/VKN';
    }
    if ($plate !== format_plate((string) $file['old_plate'])) {
        $changes[] = 'plaka';
    }

    $desc = $changes
        ? 'Dosya bilgileri güncellendi (' . implode(', ', $changes) . ')'
        : 'Dosya bilgileri güncellendi';
    add_file_log($pdo, $fileId, (int) $user['id'], $desc);

    $pdo->commit();

    json_response([
        'ok'             => true,
        'customer_name'  => $customerName,
        'customer_phone' => $customerPhone,
        'plate'          => $plate,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Güncelleme başarısız');
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);
$hours  = (int) ($_POST['hours'] ?? -1);
$revoke = !empty($_POST['revoke']);
$note   = trim($_POST['note'] ?? '');

$allowedHours = [12, 24, 48, 72, 168];

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.plate, c.name AS customer_name, c.phone AS customer_phone
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

if (!can_grant_customer_upload($user, $file)) {
    json_error('Müşteri yükleme izni vermek için yetkiniz yok', 403);
}

if ($revoke || $hours === 0) {
    $stmt = $pdo->prepare(
        'UPDATE damage_files
         SET customer_upload_until = NULL,
             customer_upload_hours = NULL,
             customer_upload_granted_by = NULL,
             customer_upload_token = NULL,
             customer_upload_note = NULL
         WHERE id = ?'
    );
    $stmt->execute([$fileId]);
    add_file_log($pdo, $fileId, (int) $user['id'], 'Müşteri evrak yükleme izni iptal edildi');
    json_response([
        'ok' => true,
        'customer_upload_active' => false,
        'whatsapp' => null,
    ]);
}

if (!in_array($hours, $allowedHours, true)) {
    json_error('Geçersiz süre. İzin verilen: ' . implode(', ', $allowedHours) . ' saat');
}

$until = (new DateTimeImmutable('now'))->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
$token = bin2hex(random_bytes(24));
$noteVal = $note !== '' ? mb_substr($note, 0, 255) : null;

$stmt = $pdo->prepare(
    'UPDATE damage_files
     SET customer_upload_until = ?,
         customer_upload_hours = ?,
         customer_upload_granted_by = ?,
         customer_upload_token = ?,
         customer_upload_note = ?
     WHERE id = ?'
);
$stmt->execute([$until, $hours, (int) $user['id'], $token, $noteVal, $fileId]);

$label = $hours >= 24 && $hours % 24 === 0
    ? ((int) ($hours / 24)) . ' gün'
    : $hours . ' saat';
add_file_log(
    $pdo,
    $fileId,
    (int) $user['id'],
    'Müşteri evrak yükleme izni verildi (' . $label . ', bitiş: ' . date('d.m.Y H:i', strtotime($until)) . ')'
);

$portalUrl = customer_portal_url((string) $file['plate'], $token);
$waText = wa_customer_docs_message(
    (string) ($file['customer_name'] ?? ''),
    (string) $file['plate'],
    (string) $file['file_number'],
    $portalUrl,
    $hours,
    $noteVal
);

json_response([
    'ok' => true,
    'customer_upload_active' => true,
    'customer_upload_until' => $until,
    'customer_upload_hours' => $hours,
    'customer_upload_remaining' => upload_remaining_label($until),
    'customer_upload_note' => $noteVal,
    'portal_url' => $portalUrl,
    'whatsapp' => wa_url($file['customer_phone'] ?? null, $waText),
    'plate' => $file['plate'],
]);

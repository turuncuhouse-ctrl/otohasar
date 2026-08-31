<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId    = (int) ($_POST['damage_file_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';
$note      = trim($_POST['note'] ?? '');

$validStatuses = array_keys(status_labels());
if (!in_array($newStatus, $validStatuses, true)) {
    json_error('Geçersiz durum');
}

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

if (!can_access_file($user, $file)) {
    json_error('Bu dosyaya erişim yetkiniz yok', 403);
}

if (!can_change_status($user, $file, $newStatus)) {
    json_error('Bu durum değişikliği için yetkiniz yok', 403);
}

$oldStatus = $file['status'];
if ($oldStatus === $newStatus) {
    json_response(['ok' => true, 'status' => $newStatus]);
}

$oldLabel = status_labels()[$oldStatus];
$newLabel = status_labels()[$newStatus];

$stmt = $pdo->prepare('UPDATE damage_files SET status = ?, note = COALESCE(NULLIF(?, ""), note) WHERE id = ?');
$stmt->execute([$newStatus, $note, $fileId]);

add_file_log($pdo, $fileId, (int) $user['id'], "Durum $oldLabel → $newLabel");

$waText = wa_status_message(
    (string) ($file['customer_name'] ?? ''),
    (string) ($file['plate'] ?? ''),
    (string) $file['file_number'],
    $newStatus
);

json_response([
    'ok'       => true,
    'status'   => $newStatus,
    'whatsapp' => wa_url($file['customer_phone'] ?? null, $waText),
    'plate'    => $file['plate'] ?? '',
]);

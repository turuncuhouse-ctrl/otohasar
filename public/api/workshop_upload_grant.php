<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);
$hours  = (int) ($_POST['hours'] ?? -1);
$revoke = !empty($_POST['revoke']);

$allowedHours = [12, 24, 48, 72, 168];

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM damage_files WHERE id = ?');
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    json_error('Dosya bulunamadı', 404);
}

if (!can_grant_workshop_upload($user, $file)) {
    json_error('Atölye yükleme izni vermek için yetkiniz yok', 403);
}

if ($revoke || $hours === 0) {
    $stmt = $pdo->prepare(
        'UPDATE damage_files
         SET workshop_upload_until = NULL,
             workshop_upload_hours = NULL,
             workshop_upload_granted_by = NULL
         WHERE id = ?'
    );
    $stmt->execute([$fileId]);
    add_file_log($pdo, $fileId, (int) $user['id'], 'Atölye evrak yükleme izni iptal edildi');
    json_response([
        'ok'                      => true,
        'workshop_upload_active'  => false,
        'workshop_upload_until'   => null,
        'workshop_upload_hours'   => null,
        'workshop_upload_remaining' => null,
    ]);
}

if (!in_array($hours, $allowedHours, true)) {
    json_error('Geçersiz süre. İzin verilen: ' . implode(', ', $allowedHours) . ' saat');
}

$until = (new DateTimeImmutable('now'))->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');

$stmt = $pdo->prepare(
    'UPDATE damage_files
     SET workshop_upload_until = ?,
         workshop_upload_hours = ?,
         workshop_upload_granted_by = ?
     WHERE id = ?'
);
$stmt->execute([$until, $hours, (int) $user['id'], $fileId]);

$label = $hours >= 24 && $hours % 24 === 0
    ? ((int) ($hours / 24)) . ' gün'
    : $hours . ' saat';
add_file_log(
    $pdo,
    $fileId,
    (int) $user['id'],
    "Atölye evrak yükleme izni verildi ($label, bitiş: " . date('d.m.Y H:i', strtotime($until)) . ')'
);

$file['workshop_upload_until'] = $until;
$file['workshop_upload_hours'] = $hours;

json_response([
    'ok'                        => true,
    'workshop_upload_active'    => true,
    'workshop_upload_until'     => $until,
    'workshop_upload_hours'     => $hours,
    'workshop_upload_remaining' => workshop_upload_remaining_label($file),
]);

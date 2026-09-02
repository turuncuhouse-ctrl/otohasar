<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);
$message = trim((string) ($_POST['customer_message'] ?? ''));

if (mb_strlen($message) > 2000) {
    json_error('Mesaj en fazla 2000 karakter olabilir');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.plate FROM damage_files df
     JOIN vehicles v ON v.id = df.vehicle_id
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
if (empty($perms['can_edit']) && empty($perms['can_grant_customer_upload'])) {
    json_error('Mesaj yazma yetkiniz yok', 403);
}

try {
    if ($message === '') {
        $pdo->prepare(
            'UPDATE damage_files SET customer_message = NULL, customer_message_at = NULL WHERE id = ?'
        )->execute([$fileId]);
        add_file_log($pdo, $fileId, (int) $user['id'], 'Müşteri mesajı temizlendi');
        json_response(['ok' => true, 'customer_message' => '', 'customer_message_at' => null]);
    }

    $pdo->prepare(
        'UPDATE damage_files SET customer_message = ?, customer_message_at = NOW() WHERE id = ?'
    )->execute([$message, $fileId]);
    add_file_log($pdo, $fileId, (int) $user['id'], 'Müşteri mesajı güncellendi');

    json_response([
        'ok' => true,
        'customer_message' => $message,
        'customer_message_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    json_error('Mesaj kaydedilemedi (migrate_v7 gerekli olabilir)', 500);
}

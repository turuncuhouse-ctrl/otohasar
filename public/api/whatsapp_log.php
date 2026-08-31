<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);
$status = $_POST['status'] ?? '';

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

$label = status_labels()[$status] ?? ($file['status'] ?? '');
add_file_log($pdo, $fileId, (int) $user['id'], "Müşteriye WhatsApp bildirimi gönderildi ($label)");

json_response(['ok' => true]);

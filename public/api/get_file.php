<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();

$fileId = (int) ($_GET['id'] ?? 0);
if ($fileId <= 0) {
    json_error('Geçersiz dosya ID');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.plate, v.brand, v.model, v.year, v.color, v.chassis_no,
            c.name AS customer_name, c.phone AS customer_phone, c.tc_vkn,
            u.name AS advisor_name
     FROM damage_files df
     JOIN vehicles v ON v.id = df.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     JOIN users u ON u.id = df.advisor_id
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

$stmt = $pdo->prepare(
    'SELECT fd.*, COALESCE(u.name, \'Müşteri\') AS uploader_name
     FROM file_documents fd
     LEFT JOIN users u ON u.id = fd.uploaded_by
     WHERE fd.damage_file_id = ?
     ORDER BY fd.uploaded_at DESC'
);
$stmt->execute([$fileId]);
$documents = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT fl.*, u.name AS user_name
     FROM file_logs fl
     JOIN users u ON u.id = fl.user_id
     WHERE fl.damage_file_id = ?
     ORDER BY fl.created_at DESC'
);
$stmt->execute([$fileId]);
$logs = $stmt->fetchAll();

$permissions = get_file_permissions($user, $file);

foreach ($documents as &$doc) {
    $doc['file_path'] = '/' . $doc['file_path'];
}
unset($doc);

json_response([
    'ok'          => true,
    'file'        => $file,
    'documents'   => $documents,
    'logs'        => $logs,
    'permissions' => $permissions,
    'status_labels' => status_labels(),
    'category_labels' => category_labels(),
]);

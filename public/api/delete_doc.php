<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$docId = (int) ($_POST['doc_id'] ?? 0);

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT fd.*, df.advisor_id, df.status, df.id AS damage_file_id
     FROM file_documents fd
     JOIN damage_files df ON df.id = fd.damage_file_id
     WHERE fd.id = ?'
);
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    json_error('Evrak bulunamadı', 404);
}

$file = [
    'advisor_id' => $doc['advisor_id'],
    'status'     => $doc['status'],
];

if (!can_access_file($user, $file)) {
    json_error('Bu dosyaya erişim yetkiniz yok', 403);
}

$perms = get_file_permissions($user, $file);
if (!$perms['can_delete_docs']) {
    json_error('Evrak silme yetkiniz yok', 403);
}

$config = app_config();
$fullPath = __DIR__ . '/../' . $doc['file_path'];
if (file_exists($fullPath)) {
    unlink($fullPath);
}

$stmt = $pdo->prepare('DELETE FROM file_documents WHERE id = ?');
$stmt->execute([$docId]);

add_file_log($pdo, (int) $doc['damage_file_id'], (int) $user['id'], 'Evrak silindi: ' . $doc['original_name']);

json_response(['ok' => true]);

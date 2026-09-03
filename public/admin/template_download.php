<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$id = (int) ($_GET['id'] ?? 0);
$template = find_insurance_template($id);
if (!$template) {
    http_response_code(404);
    exit('Şablon bulunamadı');
}

$base = realpath((string) app_config()['paths']['uploads']);
$abs = $base . DIRECTORY_SEPARATOR
    . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim((string) $template['file_path'], '/\\'));
$real = realpath($abs);
if ($base === false || $real === false || !str_starts_with($real, $base) || !is_file($real)) {
    http_response_code(404);
    exit('Dosya bulunamadı');
}

$mime = $template['mime_type'] ?: 'application/octet-stream';
$name = $template['original_name'] ?: basename($real);

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
header('Content-Length: ' . (string) filesize($real));
header('Cache-Control: private, no-store');
readfile($real);
exit;

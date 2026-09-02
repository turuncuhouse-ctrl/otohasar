<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

start_session();

$plate = portal_plate();
if ($plate === null) {
    http_response_code(401);
    exit('Oturum gerekli');
}
if (!portal_kvkk_accepted()) {
    http_response_code(403);
    exit('KVKK onayı gerekli');
}

$fileId = (int) ($_GET['file_id'] ?? 0);
$templateId = (int) ($_GET['template_id'] ?? 0);
if ($fileId <= 0 || $templateId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek');
}

$file = find_portal_file($fileId, $plate);
if (!$file) {
    http_response_code(404);
    exit('Dosya bulunamadı');
}

$company = find_insurance_company_by_name($file['insurance_company'] ?? null);
if (!$company) {
    http_response_code(403);
    exit('Bu dosya için kasko şirketi tanımlı değil');
}

$template = find_insurance_template($templateId);
if (!$template || !(int) $template['is_active']) {
    http_response_code(404);
    exit('Şablon bulunamadı');
}

if ((int) $template['insurance_company_id'] !== (int) $company['id']) {
    http_response_code(403);
    exit('Bu şablon dosyanızın kasko şirketine ait değil');
}

$uploadsRoot = realpath((string) app_config()['paths']['uploads']);
$candidate = rtrim((string) app_config()['paths']['uploads'], '/\\')
    . DIRECTORY_SEPARATOR
    . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim((string) $template['file_path'], '/\\'));
$real = realpath($candidate);

if ($uploadsRoot === false || $real === false || !str_starts_with($real, $uploadsRoot) || !is_file($real)) {
    http_response_code(404);
    exit('Dosya bulunamadı');
}

$mime = $template['mime_type'] ?: 'application/octet-stream';
$name = $template['original_name'] ?: basename($real);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($real));
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
readfile($real);
exit;

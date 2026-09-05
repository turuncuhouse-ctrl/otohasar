<?php
/**
 * Evrakı tarayıcıda aç (PDF/görsel: inline; Office: indirme).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

start_session();

$docId = (int) ($_GET['id'] ?? 0);
$forceDownload = isset($_GET['download']) && (string) $_GET['download'] === '1';

if ($docId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT fd.*, df.id AS damage_file_id, df.advisor_id, df.advisor_id_2,
            v.plate
     FROM file_documents fd
     JOIN damage_files df ON df.id = fd.damage_file_id
     JOIN vehicles v ON v.id = df.vehicle_id
     WHERE fd.id = ?'
);
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Dosya bulunamadı');
}

$allowed = false;

$user = authenticate_user();
if ($user && can_access_file($user, $doc)) {
    $allowed = true;
}

if (!$allowed) {
    $plate = portal_plate();
    if ($plate !== null && format_plate((string) $doc['plate']) === format_plate($plate)) {
        $portalFile = find_portal_file((int) $doc['damage_file_id'], $plate);
        if ($portalFile) {
            $allowed = true;
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Yetkisiz');
}

$uploadsRoot = realpath((string) app_config()['paths']['uploads']);
$rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string) $doc['file_path']), '/');
if (str_starts_with($rel, 'uploads/')) {
    $rel = substr($rel, strlen('uploads/'));
}
$candidate = rtrim((string) app_config()['paths']['uploads'], '/\\')
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $rel);
$real = realpath($candidate);

if ($uploadsRoot === false || $real === false || !str_starts_with($real, $uploadsRoot) || !is_file($real)) {
    http_response_code(404);
    exit('Dosya bulunamadı');
}

$mime = strtolower(trim((string) ($doc['mime_type'] ?? '')));
$origName = (string) ($doc['original_name'] ?? '');
$ext = strtolower(pathinfo($origName !== '' ? $origName : $real, PATHINFO_EXTENSION));

if ($mime === '' || $mime === 'application/octet-stream') {
    if ($ext === 'pdf') {
        $mime = 'application/pdf';
    } elseif (in_array($ext, ['jpg', 'jpeg'], true)) {
        $mime = 'image/jpeg';
    } elseif ($ext === 'png') {
        $mime = 'image/png';
    } elseif ($ext === 'webp') {
        $mime = 'image/webp';
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) (finfo_file($finfo, $real) ?: 'application/octet-stream'));
        finfo_close($finfo);
    }
}

$inline = !$forceDownload && (
    str_starts_with($mime, 'image/')
    || $mime === 'application/pdf'
    || $ext === 'pdf'
);

$safeName = preg_replace('/[\r\n"]+/', '', $origName !== '' ? $origName : basename($real)) ?: 'evrak';
$disposition = $inline ? 'inline' : 'attachment';
$asciiName = preg_replace('/[^\x20-\x7E]+/', '_', $safeName) ?: 'evrak';

header('Content-Type: ' . $mime);
header(
    'Content-Disposition: ' . $disposition
    . '; filename="' . $asciiName . '"'
    . "; filename*=UTF-8''" . rawurlencode($safeName)
);
header('Content-Length: ' . (string) filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');

readfile($real);
exit;

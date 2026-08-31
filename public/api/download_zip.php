<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();

$fileId = (int) ($_GET['file_id'] ?? 0);
$plateQ = trim($_GET['plate'] ?? '');

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZIP desteklenmiyor (php-zip eklentisi gerekli)';
    exit;
}

$pdo = db();
$files = [];

if ($fileId > 0) {
    $stmt = $pdo->prepare(
        'SELECT df.id, df.file_number, df.advisor_id, df.status, v.plate
         FROM damage_files df
         JOIN vehicles v ON v.id = df.vehicle_id
         WHERE df.id = ?'
    );
    $stmt->execute([$fileId]);
    $row = $stmt->fetch();
    if (!$row || !can_access_file($user, $row)) {
        http_response_code(403);
        echo 'Yetkisiz';
        exit;
    }
    $files = [$row];
} elseif ($plateQ !== '') {
    $stmt = $pdo->prepare(
        'SELECT df.id, df.file_number, df.advisor_id, df.status, v.plate
         FROM damage_files df
         JOIN vehicles v ON v.id = df.vehicle_id
         WHERE v.plate LIKE ?
         ORDER BY df.id'
    );
    $stmt->execute(['%' . format_plate($plateQ) . '%']);
    foreach ($stmt->fetchAll() as $row) {
        if (can_access_file($user, $row)) {
            $files[] = $row;
        }
    }
} else {
    http_response_code(400);
    echo 'file_id veya plate gerekli';
    exit;
}

if (!$files) {
    http_response_code(404);
    echo 'Dosya bulunamadı';
    exit;
}

$cats = category_labels();
$zip = new ZipArchive();
$tmp = tempnam(sys_get_temp_dir(), 'otohasar_zip_');
if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'ZIP oluşturulamadı';
    exit;
}

$added = 0;
$config = app_config();
$publicRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

foreach ($files as $file) {
    $plateFolder = safe_zip_name(format_plate($file['plate']));
    $fileFolder = safe_zip_name($file['file_number']);

    $stmt = $pdo->prepare('SELECT * FROM file_documents WHERE damage_file_id = ? ORDER BY category, id');
    $stmt->execute([(int) $file['id']]);
    $docs = $stmt->fetchAll();

    foreach ($docs as $doc) {
        $path = $publicRoot . '/' . ltrim(str_replace(['..', '\\'], '', $doc['file_path']), '/');
        if (!is_file($path)) {
            continue;
        }
        $cat = $cats[$doc['category']] ?? $doc['category'];
        $catFolder = safe_zip_name($cat);
        $orig = safe_zip_name($doc['original_name']);
        $entry = $plateFolder . '/' . $fileFolder . '/' . $catFolder . '/' . $orig;
        // avoid overwrite
        $i = 1;
        $base = pathinfo($orig, PATHINFO_FILENAME);
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        while ($zip->locateName($entry) !== false) {
            $entry = $plateFolder . '/' . $fileFolder . '/' . $catFolder . '/' . $base . '_' . $i . ($ext ? '.' . $ext : '');
            $i++;
        }
        $zip->addFile($path, $entry);
        $added++;
    }
}

$zip->close();

if ($added === 0) {
    @unlink($tmp);
    http_response_code(404);
    echo 'İndirilecek evrak yok';
    exit;
}

$downloadName = count($files) === 1
    ? safe_zip_name($files[0]['plate'] . '_' . $files[0]['file_number']) . '.zip'
    : 'evraklar_' . date('Ymd_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string) filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
@unlink($tmp);
exit;

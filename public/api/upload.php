<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId   = (int) ($_POST['damage_file_id'] ?? 0);
$category = $_POST['category'] ?? '';

$validCategories = array_keys(category_labels());
if (!in_array($category, $validCategories, true)) {
    json_error('Geçersiz kategori');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.plate FROM damage_files df JOIN vehicles v ON v.id = df.vehicle_id WHERE df.id = ?'
);
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    json_error('Dosya bulunamadı', 404);
}

if (!can_access_file($user, $file)) {
    json_error('Bu dosyaya erişim yetkiniz yok', 403);
}

if (!can_upload_category($user, $file, $category)) {
    json_error('Bu kategoriye yükleme yetkiniz yok', 403);
}

$incoming = normalize_uploaded_files($_FILES['files'] ?? []);
if (!$incoming) {
    json_error('Dosya seçilmedi — lütfen galeriden fotoğraf seçip tekrar deneyin');
}

if (count($incoming) > 20) {
    json_error('En fazla 20 dosya yüklenebilir');
}

$config = app_config();
$uploadDir = $config['paths']['uploads'] . '/' . $fileId;
ensure_upload_guards($uploadDir);

$uploaded = [];
$errors   = [];

foreach ($incoming as $item) {
    $origName = $item['name'];
    $error = $item['error'];
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = upload_error_message($error, $origName);
        continue;
    }

    $tmpPath = $item['tmp_name'];
    $size    = $item['size'];

    if ($size <= 0) {
        $errors[] = ($origName !== '' ? $origName . ': ' : '') . 'Boş dosya';
        continue;
    }

    if ($size > $config['app']['upload_max']) {
        $errors[] = "$origName: 20MB limiti aşıldı";
        continue;
    }

    $validated = validate_document_mime($tmpPath, $origName);
    if (!$validated) {
        $errors[] = upload_validation_error($tmpPath, $origName);
        continue;
    }

    $filename = random_filename($validated['ext']);
    $destPath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/' . $fileId . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        $errors[] = "$origName: Kaydedilemedi";
        continue;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO file_documents (damage_file_id, category, file_path, original_name, mime_type, file_size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$fileId, $category, $relativePath, $origName, $validated['mime'], $size, $user['id']]);
    $docId = (int) $pdo->lastInsertId();

    $uploaded[] = [
        'id'            => $docId,
        'category'      => $category,
        'file_path'     => '/' . $relativePath,
        'original_name' => $origName,
        'mime_type'     => $validated['mime'],
        'file_size'     => $size,
    ];
}

if (!empty($uploaded)) {
    $catLabel = category_labels()[$category];
    $count = count($uploaded);
    add_file_log($pdo, $fileId, (int) $user['id'], "$count evrak yüklendi ($catLabel)");
}

if (empty($uploaded)) {
    json_error(!empty($errors) ? implode(' · ', $errors) : 'Dosya yüklenemedi');
}

json_response(['ok' => true, 'uploaded' => $uploaded, 'errors' => $errors]);

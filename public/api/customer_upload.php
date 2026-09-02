<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

start_session();
verify_api_csrf();

$plate = portal_plate();
if ($plate === null) {
    json_error('Plaka oturumu gerekli', 401);
}

$fileId   = (int) ($_POST['damage_file_id'] ?? 0);
$category = $_POST['category'] ?? '';

$allowed = customer_upload_categories();
if (!isset($allowed[$category])) {
    json_error('Geçersiz kategori');
}

$file = find_portal_file($fileId, $plate);
if (!$file) {
    json_error('Dosya bulunamadı', 404);
}

if (!is_customer_upload_granted($file)) {
    json_error('Yükleme izniniz yok veya süresi dolmuş. Lütfen servisinizle iletişime geçin.', 403);
}

if (empty($_FILES['files'])) {
    json_error('Dosya seçilmedi');
}

$pdo = db();
$config = app_config();
$uploadDir = $config['paths']['uploads'] . '/' . $fileId;
ensure_upload_guards($uploadDir);

$uploaded = [];
$errors   = [];
$fileCount = is_array($_FILES['files']['name'] ?? null) ? count($_FILES['files']['name']) : 0;

if ($fileCount > 8) {
    json_error('En fazla 8 dosya yüklenebilir');
}

for ($i = 0; $i < $fileCount; $i++) {
    $error = $_FILES['files']['error'][$i];
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = 'Yükleme hatası: ' . $_FILES['files']['name'][$i];
        continue;
    }

    $tmpPath  = $_FILES['files']['tmp_name'][$i];
    $origName = $_FILES['files']['name'][$i];
    $size     = (int) $_FILES['files']['size'][$i];

    if ($size > $config['app']['upload_max']) {
        $errors[] = "$origName: 10MB limiti aşıldı";
        continue;
    }

    $validated = validate_upload_mime($tmpPath, $origName);
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
         VALUES (?, ?, ?, ?, ?, ?, NULL)'
    );
    $stmt->execute([$fileId, $category, $relativePath, $origName, $validated['mime'], $size]);
    $docId = (int) $pdo->lastInsertId();

    $uploaded[] = [
        'id'            => $docId,
        'category'      => $category,
        'file_path'     => '/' . $relativePath,
        'original_name' => $origName,
    ];
}

if (!empty($uploaded)) {
    $catLabel = $allowed[$category];
    $count = count($uploaded);
    $logUserId = (int) ($file['customer_upload_granted_by'] ?: $file['advisor_id']);
    add_file_log($pdo, $fileId, $logUserId, "Müşteri $count evrak yükledi ($catLabel)");
}

if (empty($uploaded)) {
    json_error(!empty($errors) ? implode(' · ', $errors) : 'Dosya yüklenemedi');
}

json_response(['ok' => true, 'uploaded' => $uploaded, 'errors' => $errors]);

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();
verify_api_csrf();

$fileId = (int) ($_POST['damage_file_id'] ?? 0);
$kind = trim((string) ($_POST['kind'] ?? 'status')); // status | docs | custom
$templateId = (int) ($_POST['template_id'] ?? 0);

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
    json_error('Yetkisiz', 403);
}

$extra = [];
if ($kind === 'docs' || ($kind === 'custom')) {
    $hours = (int) ($file['customer_upload_hours'] ?? 48);
    if ($hours <= 0) {
        $hours = 48;
    }
    $token = null;
    if (is_customer_upload_granted($file)) {
        $token = $file['customer_upload_token'] ?? null;
        $extra['hours'] = (int) ($file['customer_upload_hours'] ?: $hours);
        $extra['note'] = $file['customer_upload_note'] ?? null;
    } else {
        $extra['hours'] = $hours;
    }
    $extra['portal_url'] = customer_portal_url($file['plate'], $token);
}

if ($kind === 'status') {
    $text = wa_status_message(
        (string) $file['customer_name'],
        (string) $file['plate'],
        (string) $file['file_number'],
        (string) $file['status'],
        $file['work_order_no'] ?? null
    );
} elseif ($kind === 'docs') {
    $text = wa_customer_docs_message(
        (string) $file['customer_name'],
        (string) $file['plate'],
        (string) $file['file_number'],
        (string) $extra['portal_url'],
        (int) $extra['hours'],
        $extra['note'] ?? null,
        $file['work_order_no'] ?? null
    );
} elseif ($kind === 'custom') {
    $tpl = find_wa_template($templateId);
    if (!$tpl || !(int) $tpl['is_active']) {
        json_error('Şablon bulunamadı', 404);
    }
    $text = wa_compose_message((string) $tpl['body'], $file, $extra);
} else {
    json_error('Geçersiz şablon türü');
}

$url = wa_url($file['customer_phone'] ?? null, $text);
if (!$url) {
    json_error('Müşteri telefonu yok veya geçersiz', 400);
}

json_response([
    'ok' => true,
    'whatsapp' => $url,
    'plate' => $file['plate'],
    'preview' => $text,
]);

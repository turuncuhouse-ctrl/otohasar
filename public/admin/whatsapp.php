<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    echo 'Yetkisiz erişim';
    exit;
}

$pageTitle = 'WhatsApp Şablonları';
$activeNav = 'admin';
$message = '';
$error = '';

$statusTpl = app_setting('wa_status_template', wa_default_status_template()) ?? wa_default_status_template();
$docsTpl = app_setting('wa_customer_docs_template', wa_default_customer_docs_template()) ?? wa_default_customer_docs_template();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $newStatus = trim($_POST['wa_status_template'] ?? '');
    $newDocs = trim($_POST['wa_customer_docs_template'] ?? '');
    if ($newStatus === '' || $newDocs === '') {
        $error = 'Şablonlar boş olamaz';
    } else {
        app_setting_set('wa_status_template', $newStatus);
        app_setting_set('wa_customer_docs_template', $newDocs);
        $statusTpl = $newStatus;
        $docsTpl = $newDocs;
        $message = 'Şablonlar kaydedildi';
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>WhatsApp Mesaj Şablonları</h1>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<p class="dash-sub" style="margin-bottom:1rem">
    Kullanılabilir değişkenler:
    <code>{name}</code> <code>{plate}</code> <code>{file_number}</code> <code>{status_text}</code>
    <code>{status_label}</code> <code>{work_order_no}</code> <code>{work_order_line}</code>
    <code>{portal_url}</code> <code>{hours_label}</code> <code>{note_line}</code>
</p>

<form method="post" class="admin-form-card" style="max-width:720px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-group">
        <label>Durum bildirimi şablonu</label>
        <textarea class="form-input" name="wa_status_template" rows="8" required><?= e($statusTpl) ?></textarea>
    </div>
    <div class="form-group">
        <label>Müşteri evrak yükleme şablonu</label>
        <textarea class="form-input" name="wa_customer_docs_template" rows="10" required><?= e($docsTpl) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Kaydet</button>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

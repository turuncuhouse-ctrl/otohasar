<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'WhatsApp Şablonları';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';

$statusTpl = app_setting('wa_status_template', wa_default_status_template()) ?? wa_default_status_template();
$docsTpl = app_setting('wa_customer_docs_template', wa_default_customer_docs_template()) ?? wa_default_customer_docs_template();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'save_system';

    if ($action === 'save_system') {
        $newStatus = trim($_POST['wa_status_template'] ?? '');
        $newDocs = trim($_POST['wa_customer_docs_template'] ?? '');
        if ($newStatus === '' || $newDocs === '') {
            $error = 'Sistem şablonları boş olamaz';
        } else {
            app_setting_set('wa_status_template', $newStatus);
            app_setting_set('wa_customer_docs_template', $newDocs);
            $statusTpl = $newStatus;
            $docsTpl = $newDocs;
            $message = 'Sistem şablonları kaydedildi';
        }
    } elseif ($action === 'save_custom') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($title === '' || $body === '') {
            $error = 'Başlık ve mesaj metni zorunlu';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare(
                        'UPDATE wa_templates SET title=?, body=?, sort_order=?, is_active=? WHERE id=?'
                    )->execute([$title, $body, $sort, $active, $id]);
                    $message = 'Şablon güncellendi';
                } else {
                    $pdo->prepare(
                        'INSERT INTO wa_templates (title, body, sort_order, is_active) VALUES (?,?,?,?)'
                    )->execute([$title, $body, $sort, $active]);
                    $message = 'Yeni WhatsApp şablonu eklendi';
                }
                header('Location: /admin/whatsapp.php?ok=1');
                exit;
            } catch (Throwable $e) {
                $error = 'Şablon kaydedilemedi (migrate_v11 gerekli olabilir)';
            }
        }
    } elseif ($action === 'delete_custom') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM wa_templates WHERE id=?')->execute([$id]);
            $message = 'Şablon silindi';
        } catch (Throwable $e) {
            $error = 'Silinemedi';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'CSRF hatası';
}

if (isset($_GET['ok'])) {
    $message = 'Kayıt güncellendi';
}

$custom = wa_custom_templates(false);
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($custom as $row) {
    if ((int) $row['id'] === $editId) {
        $edit = $row;
        break;
    }
}
$isEdit = $edit !== null;
$nextSort = 10;
if (!$isEdit && $custom) {
    $max = 0;
    foreach ($custom as $row) {
        $max = max($max, (int) $row['sort_order']);
    }
    $nextSort = $max + 10;
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>WhatsApp Mesaj Şablonları</h1>
    <div class="page-header-actions">
        <a href="/admin/whatsapp.php" class="btn btn-primary btn-sm">+ Yeni Şablon</a>
        <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<p class="dash-sub" style="margin-bottom:1rem">
    Kullanılabilir değişkenler:
    <code>{name}</code> <code>{plate}</code> <code>{file_number}</code> <code>{status_text}</code>
    <code>{status_label}</code> <code>{work_order_no}</code> <code>{work_order_line}</code>
    <code>{portal_url}</code> <code>{hours_label}</code> <code>{note_line}</code>
    <code>{insurance}</code> <code>{phone}</code>
</p>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_custom">
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$edit['id'] : 0 ?>">
        <h2><?= $isEdit ? 'Şablon Düzenle' : 'Yeni WhatsApp Şablonu' ?></h2>
        <p class="form-hint">Dosya ekranından seçip müşteriye gönderebilirsiniz.</p>
        <div class="form-group">
            <label>Başlık</label>
            <input class="form-input" name="title" required maxlength="120"
                   value="<?= e($edit['title'] ?? '') ?>" placeholder="Örn: Ekspertiz randevu">
        </div>
        <div class="form-group">
            <label>Mesaj metni</label>
            <textarea class="form-input" name="body" rows="8" required
                      placeholder="Merhaba {name},&#10;&#10;{plate} plakalı aracınız..."><?= e($edit['body'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Sıra</label>
            <input class="form-input" type="number" name="sort_order"
                   value="<?= (int)($edit['sort_order'] ?? $nextSort) ?>">
        </div>
        <label class="check-row">
            <input type="checkbox" name="is_active" <?= !$isEdit || !empty($edit['is_active']) ? 'checked' : '' ?>>
            Aktif (dosya ekranında görünsün)
        </label>
        <button class="btn btn-primary btn-block" type="submit">
            <?= $isEdit ? 'Güncelle' : 'Şablon Ekle' ?>
        </button>
        <?php if ($isEdit): ?>
        <a class="btn btn-ghost btn-block" href="/admin/whatsapp.php">İptal / Yeni şablon</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <h2 style="font-size:1rem;margin-bottom:.75rem">Özel şablonlar</h2>
        <table class="report-table">
            <thead><tr><th>Başlık</th><th>Sıra</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($custom as $row): ?>
            <tr class="<?= empty($row['is_active']) ? 'row-inactive' : '' ?>">
                <td>
                    <strong><?= e($row['title']) ?></strong>
                    <br><small class="text-muted"><?php
                        $preview = preg_replace('/\s+/', ' ', (string)$row['body']) ?? '';
                        echo e(mb_strlen($preview) > 80 ? mb_substr($preview, 0, 80) . '…' : $preview);
                    ?></small>
                </td>
                <td><?= (int)$row['sort_order'] ?></td>
                <td><?= !empty($row['is_active']) ? 'Evet' : 'Hayır' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-primary" href="?edit=<?= (int)$row['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_custom">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$custom): ?>
            <tr><td colspan="4" class="empty-state">Henüz özel şablon yok. Soldan ekleyin.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form method="post" class="admin-form-card" style="max-width:900px;margin-top:1.5rem">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_system">
    <h2>Sistem şablonları</h2>
    <p class="form-hint">Durum değişiminde ve müşteri evrak yükleme izninde otomatik kullanılır.</p>
    <div class="form-group">
        <label>Durum bildirimi şablonu</label>
        <textarea class="form-input" name="wa_status_template" rows="7" required><?= e($statusTpl) ?></textarea>
    </div>
    <div class="form-group">
        <label>Müşteri evrak yükleme şablonu</label>
        <textarea class="form-input" name="wa_customer_docs_template" rows="8" required><?= e($docsTpl) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Sistem şablonlarını kaydet</button>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

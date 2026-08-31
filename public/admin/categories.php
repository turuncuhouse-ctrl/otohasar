<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Evrak Kategorileri';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $code = $code !== '' ? slugify_code($code) : slugify_code($label);
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $required = isset($_POST['is_required']) ? 1 : 0;
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($label === '') {
            $error = 'Etiket zorunlu';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE app_categories SET code=?, label=?, sort_order=?, is_required=?, is_active=? WHERE id=?')
                        ->execute([$code, $label, $sort, $required, $active, $id]);
                } else {
                    $pdo->prepare('INSERT INTO app_categories (code, label, sort_order, is_required, is_active) VALUES (?,?,?,?,?)')
                        ->execute([$code, $label, $sort, $required, $active]);
                }
                $message = 'Kategori kaydedildi';
            } catch (Throwable $e) {
                $error = 'Kayıt hatası (kod benzersiz olmalı)';
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM app_categories WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        $message = 'Kategori silindi';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'CSRF hatası';
}

$rows = $pdo->query('SELECT * FROM app_categories ORDER BY sort_order, id')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Evrak Kategorileri</h1>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <h2><?= $edit ? 'Kategori Düzenle' : 'Yeni Kategori' ?></h2>
        <div class="form-group"><label>Ad</label><input class="form-input" name="label" required value="<?= e($edit['label'] ?? '') ?>"></div>
        <div class="form-group"><label>Kod</label><input class="form-input" name="code" placeholder="otomatik" value="<?= e($edit['code'] ?? '') ?>"></div>
        <div class="form-group"><label>Sıra</label><input class="form-input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 100) ?>"></div>
        <label class="check-row"><input type="checkbox" name="is_required" <?= !empty($edit['is_required']) ? 'checked' : '' ?>> Zorunlu evrak</label>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$edit || !empty($edit['is_active']) ? 'checked' : '' ?>> Aktif</label>
        <button class="btn btn-primary btn-block" type="submit">Kaydet</button>
    </form>
    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Kategori</th><th>Kod</th><th>Zorunlu</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['label']) ?></td>
                <td><?= e($r['code']) ?></td>
                <td><?= $r['is_required'] ? 'Evet' : 'Hayır' ?></td>
                <td><?= $r['is_active'] ? 'Evet' : 'Hayır' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$r['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>

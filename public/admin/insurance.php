<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Sigorta Şirketleri';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $labor = (float) ($_POST['labor_discount'] ?? 0);
        $parts = (float) ($_POST['parts_discount'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '') {
            $error = 'Şirket adı zorunlu';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE insurance_companies SET name=?, labor_discount=?, parts_discount=?, note=?, is_active=? WHERE id=?')
                        ->execute([$name, $labor, $parts, $note ?: null, $active, $id]);
                } else {
                    $pdo->prepare('INSERT INTO insurance_companies (name, labor_discount, parts_discount, note, is_active) VALUES (?,?,?,?,?)')
                        ->execute([$name, $labor, $parts, $note ?: null, $active]);
                }
                $message = 'Sigorta şirketi kaydedildi';
            } catch (Throwable $e) {
                $error = 'Kayıt hatası (isim benzersiz olmalı)';
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM insurance_companies WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        $message = 'Silindi';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'CSRF hatası';
}

$rows = $pdo->query('SELECT * FROM insurance_companies ORDER BY name')->fetchAll();
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
    <h1>Anlaşmalı Sigorta Şirketleri</h1>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <h2><?= $edit ? 'Şirket Düzenle' : 'Yeni Şirket' ?></h2>
        <div class="form-group"><label>Şirket Adı</label><input class="form-input" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>İşçilik İskontosu %</label><input class="form-input" type="number" step="0.01" min="0" max="100" name="labor_discount" value="<?= e((string)($edit['labor_discount'] ?? '0')) ?>"></div>
            <div class="form-group"><label>Parça İskontosu %</label><input class="form-input" type="number" step="0.01" min="0" max="100" name="parts_discount" value="<?= e((string)($edit['parts_discount'] ?? '0')) ?>"></div>
        </div>
        <div class="form-group"><label>Not</label><input class="form-input" name="note" value="<?= e($edit['note'] ?? '') ?>"></div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$edit || !empty($edit['is_active']) ? 'checked' : '' ?>> Aktif / Anlaşmalı</label>
        <button class="btn btn-primary btn-block" type="submit">Kaydet</button>
    </form>
    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Şirket</th><th>İşçilik %</th><th>Parça %</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['name']) ?><?php if ($r['note']): ?><br><small class="text-muted"><?= e($r['note']) ?></small><?php endif; ?></td>
                <td><?= e((string)$r['labor_discount']) ?></td>
                <td><?= e((string)$r['parts_discount']) ?></td>
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

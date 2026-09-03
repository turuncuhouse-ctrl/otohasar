<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Tanıtım Sunumu';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $eyebrow = trim($_POST['eyebrow'] ?? '') ?: null;
            $body = trim($_POST['body'] ?? '');
            $bullets = trim($_POST['bullets'] ?? '') ?: null;
            $sort = (int) ($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($title === '' || $body === '') {
                $error = 'Başlık ve metin zorunlu';
            } elseif ($id > 0) {
                $pdo->prepare('UPDATE tour_slides SET title=?, eyebrow=?, body=?, bullets=?, sort_order=?, is_active=? WHERE id=?')
                    ->execute([$title, $eyebrow, $body, $bullets, $sort, $active, $id]);
                $message = 'Slayt güncellendi';
            } else {
                $pdo->prepare('INSERT INTO tour_slides (title, eyebrow, body, bullets, sort_order, is_active) VALUES (?,?,?,?,?,?)')
                    ->execute([$title, $eyebrow, $body, $bullets, $sort, $active]);
                $message = 'Slayt eklendi';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM tour_slides WHERE id=?')->execute([$id]);
            $message = 'Slayt silindi';
        }
    }
}

$slides = tour_slides(false);
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($slides as $s) {
    if ((int) $s['id'] === $editId) {
        $edit = $s;
        break;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Tanıtım Sunumu</h1>
        <p class="dash-sub">Varsayılan görünürlük: Sistem Admin + Servis Müdürü. Diğer gruplar için Kullanıcı Grupları → “Tanıtım sunumu” iznini açın.</p>
    </div>
    <div class="header-actions">
        <a href="/tour.php" class="btn btn-ghost btn-sm" target="_blank">Önizle</a>
        <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <h2><?= $edit ? 'Slayt Düzenle' : 'Yeni Slayt' ?></h2>
        <div class="form-group"><label>Üst etiket (eyebrow)</label><input class="form-input" name="eyebrow" value="<?= e($edit['eyebrow'] ?? '') ?>" placeholder="Örn. Hasar süreci"></div>
        <div class="form-group"><label>Başlık</label><input class="form-input" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
        <div class="form-group"><label>Ana metin</label><textarea class="form-input" name="body" rows="7" required><?= e($edit['body'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Madde işaretleri (her satır bir madde)</label><textarea class="form-input" name="bullets" rows="5"><?= e($edit['bullets'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Sıra</label><input class="form-input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 100) ?>"></div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$edit || !empty($edit['is_active']) ? 'checked' : '' ?>> Aktif</label>
        <button class="btn btn-primary btn-block" type="submit"><?= $edit ? 'Kaydet' : 'Ekle' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost btn-block" href="/admin/tour.php">İptal</a><?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Sıra</th><th>Bölüm</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($slides as $s): ?>
            <tr>
                <td><?= (int)$s['sort_order'] ?></td>
                <td>
                    <?php if (!empty($s['eyebrow'])): ?><span class="muted"><?= e($s['eyebrow']) ?></span><br><?php endif; ?>
                    <?= e($s['title']) ?>
                </td>
                <td><?= $s['is_active'] ? 'Aktif' : 'Pasif' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-ghost" href="/admin/tour.php?edit=<?= (int)$s['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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

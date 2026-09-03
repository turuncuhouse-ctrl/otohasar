<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Durumlar';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';
$palette = status_color_palette();
$colors = array_keys($palette);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $color = $_POST['color_class'] ?? 'status-slate';
        if (!in_array($color, $colors, true)) {
            $color = 'status-slate';
        }
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($label === '') {
            $error = 'Görünen ad zorunlu';
        } else {
            try {
                if ($id > 0) {
                    // Keep existing code when editing — changing it would orphan files
                    $stmt = $pdo->prepare('SELECT code FROM app_statuses WHERE id = ?');
                    $stmt->execute([$id]);
                    $existingCode = $stmt->fetchColumn();
                    if ($existingCode === false) {
                        $error = 'Durum bulunamadı';
                    } else {
                        $pdo->prepare(
                            'UPDATE app_statuses SET label=?, color_class=?, sort_order=?, is_active=? WHERE id=?'
                        )->execute([$label, $color, $sort, $active, $id]);
                        $message = 'Durum güncellendi';
                        $editId = $id;
                    }
                } else {
                    if ($code === '') {
                        $code = slugify_code($label);
                    } else {
                        $code = slugify_code($code);
                    }
                    $pdo->prepare(
                        'INSERT INTO app_statuses (code, label, color_class, sort_order, is_active) VALUES (?,?,?,?,?)'
                    )->execute([$code, $label, $color, $sort, $active]);
                    $message = 'Yeni durum eklendi';
                    $editId = 0;
                }
            } catch (Throwable $e) {
                $error = 'Kayıt hatası (kod benzersiz olmalı)';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT code FROM app_statuses WHERE id = ?');
        $stmt->execute([$id]);
        $code = $stmt->fetchColumn();
        if ($code) {
            $inUse = $pdo->prepare('SELECT COUNT(*) FROM damage_files WHERE status = ?');
            $inUse->execute([$code]);
            if ((int) $inUse->fetchColumn() > 0) {
                $error = 'Bu durum kullanımda; silmek yerine pasife alın.';
            } else {
                $pdo->prepare('DELETE FROM app_statuses WHERE id=?')->execute([$id]);
                $message = 'Durum silindi';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'CSRF hatası';
}

$rows = $pdo->query('SELECT * FROM app_statuses ORDER BY sort_order, id')->fetchAll();
if (!isset($editId)) {
    $editId = (int) ($_GET['edit'] ?? 0);
}
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}
$isEdit = $edit !== null;
$selectedColor = $edit['color_class'] ?? 'status-blue';
if (!isset($palette[$selectedColor])) {
    $selectedColor = 'status-slate';
}
$nextSort = 10;
if (!$isEdit && $rows) {
    $maxSort = 0;
    foreach ($rows as $r) {
        $maxSort = max($maxSort, (int) $r['sort_order']);
    }
    $nextSort = $maxSort + 10;
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Araç / Dosya Durumları</h1>
    <div class="page-header-actions">
        <a href="/admin/statuses.php" class="btn btn-primary btn-sm">+ Yeni Durum</a>
        <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
    </div>
</div>

<p class="dash-sub" style="margin-bottom:1rem">
    Kanban ve liste panosundaki dosya durumlarını ekleyin veya güncelleyin. Renkleri paletten seçin.
</p>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card" id="statusForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$edit['id'] : 0 ?>">
        <h2><?= $isEdit ? 'Durum Düzenle' : 'Yeni Durum Ekle' ?></h2>

        <div class="form-group">
            <label for="statusLabel">Görünen ad</label>
            <input class="form-input" id="statusLabel" name="label" required maxlength="100"
                   value="<?= e($edit['label'] ?? '') ?>" placeholder="Örn: Eksper Bekliyor">
        </div>

        <div class="form-group">
            <label for="statusCode">Kod <?= $isEdit ? '' : '(opsiyonel)' ?></label>
            <?php if ($isEdit): ?>
            <input class="form-input" id="statusCode" value="<?= e($edit['code']) ?>" readonly disabled>
            <p class="form-hint">Kod dosyalara bağlıdır; değiştirilemez.</p>
            <?php else: ?>
            <input class="form-input" id="statusCode" name="code" placeholder="Boş bırakırsanız otomatik"
                   value="">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Renk</label>
            <div class="color-palette" role="radiogroup" aria-label="Durum rengi">
                <?php foreach ($palette as $class => $name): ?>
                <label class="color-swatch <?= $class ?>" title="<?= e($name) ?>">
                    <input type="radio" name="color_class" value="<?= e($class) ?>"
                           <?= $selectedColor === $class ? 'checked' : '' ?>>
                    <span class="swatch-dot"></span>
                    <span class="swatch-name"><?= e($name) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="color-preview">
                Önizleme:
                <span class="status-pill small <?= e($selectedColor) ?>" id="colorPreview">
                    <?= e($edit['label'] ?? 'Durum') ?>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="sortOrder">Sıra (küçük sayı önce)</label>
            <input class="form-input" id="sortOrder" type="number" name="sort_order"
                   value="<?= (int)($edit['sort_order'] ?? $nextSort) ?>">
        </div>

        <label class="check-row">
            <input type="checkbox" name="is_active" <?= !$isEdit || !empty($edit['is_active']) ? 'checked' : '' ?>>
            Aktif (panoda görünsün)
        </label>

        <button class="btn btn-primary btn-block" type="submit">
            <?= $isEdit ? 'Güncelle' : 'Durum Ekle' ?>
        </button>
        <?php if ($isEdit): ?>
        <a class="btn btn-ghost btn-block" href="/admin/statuses.php">İptal / Yeni durum</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead>
            <tr>
                <th>Durum</th>
                <th>Kod</th>
                <th>Sıra</th>
                <th>Aktif</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr class="<?= empty($r['is_active']) ? 'row-inactive' : '' ?>">
                <td>
                    <span class="status-pill small <?= e($r['color_class']) ?>"><?= e($r['label']) ?></span>
                </td>
                <td><code><?= e($r['code']) ?></code></td>
                <td><?= (int)$r['sort_order'] ?></td>
                <td><?= !empty($r['is_active']) ? 'Evet' : 'Hayır' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-primary" href="?edit=<?= (int)$r['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Bu durum silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
            <tr><td colspan="5" class="empty-state">Henüz durum yok. Soldan ekleyin.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    var preview = document.getElementById('colorPreview');
    var label = document.getElementById('statusLabel');
    if (!preview) return;

    function syncPreviewText() {
        preview.textContent = (label && label.value.trim()) ? label.value.trim() : 'Durum';
    }
    function syncPreviewColor() {
        var checked = document.querySelector('input[name="color_class"]:checked');
        if (!checked) return;
        preview.className = 'status-pill small ' + checked.value;
        document.querySelectorAll('.color-swatch').forEach(function(sw) {
            sw.classList.toggle('is-selected', sw.querySelector('input') === checked);
        });
    }
    document.querySelectorAll('input[name="color_class"]').forEach(function(r) {
        r.addEventListener('change', syncPreviewColor);
    });
    if (label) label.addEventListener('input', syncPreviewText);
    syncPreviewColor();
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

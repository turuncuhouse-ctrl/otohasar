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

$checkOptions = cover_form_check_options(false);
$hasFormMap = schema_column_exists($pdo, 'app_categories', 'form_field_code');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (mb_strlen($description) > 255) {
            $description = mb_substr($description, 0, 255);
        }
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $required = isset($_POST['is_required']) ? 1 : 0;
        $active = isset($_POST['is_active']) ? 1 : 0;
        $formField = trim($_POST['form_field_code'] ?? '');
        if ($formField !== '' && !isset($checkOptions[$formField])) {
            $formField = '';
        }

        if ($label === '') {
            flash_set('error', 'Ad zorunlu');
            admin_redirect('/admin/categories.php' . ($id > 0 ? ('?edit=' . $id) : ''));
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT code FROM app_categories WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() === false) {
                    flash_set('error', 'Kategori bulunamadı');
                    admin_redirect('/admin/categories.php');
                }
                if ($hasFormMap) {
                    $pdo->prepare(
                        'UPDATE app_categories SET label=?, description=?, form_field_code=?, sort_order=?, is_required=?, is_active=? WHERE id=?'
                    )->execute([
                        $label,
                        $description !== '' ? $description : null,
                        $formField !== '' ? $formField : null,
                        $sort,
                        $required,
                        $active,
                        $id,
                    ]);
                } else {
                    $pdo->prepare(
                        'UPDATE app_categories SET label=?, description=?, sort_order=?, is_required=?, is_active=? WHERE id=?'
                    )->execute([
                        $label,
                        $description !== '' ? $description : null,
                        $sort,
                        $required,
                        $active,
                        $id,
                    ]);
                }
                flash_set('success', 'Kategori güncellendi');
                admin_redirect('/admin/categories.php?edit=' . $id);
            }

            $code = $code !== '' ? slugify_code($code) : slugify_code($label);
            if ($hasFormMap) {
                $pdo->prepare(
                    'INSERT INTO app_categories (code, label, description, form_field_code, sort_order, is_required, is_active)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $code,
                    $label,
                    $description !== '' ? $description : null,
                    $formField !== '' ? $formField : null,
                    $sort,
                    $required,
                    $active,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO app_categories (code, label, description, sort_order, is_required, is_active)
                     VALUES (?,?,?,?,?,?)'
                )->execute([
                    $code,
                    $label,
                    $description !== '' ? $description : null,
                    $sort,
                    $required,
                    $active,
                ]);
            }
            flash_set('success', 'Yeni kategori eklendi');
            admin_redirect('/admin/categories.php');
        } catch (Throwable $e) {
            flash_set('error', 'Kayıt hatası (kod benzersiz olmalı)');
            admin_redirect('/admin/categories.php' . ($id > 0 ? ('?edit=' . $id) : ''));
        }
    } elseif ($action === 'move') {
        $id = (int) ($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $ordered = $pdo->query('SELECT id FROM app_categories ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
        $idx = array_search((string) $id, array_map('strval', $ordered), true);
        if ($idx !== false) {
            $swapWith = $dir === 'up' ? $idx - 1 : ($dir === 'down' ? $idx + 1 : null);
            if ($swapWith !== null && isset($ordered[$swapWith])) {
                $tmp = $ordered[$idx];
                $ordered[$idx] = $ordered[$swapWith];
                $ordered[$swapWith] = $tmp;
                $upd = $pdo->prepare('UPDATE app_categories SET sort_order=? WHERE id=?');
                foreach ($ordered as $i => $rowId) {
                    $upd->execute([($i + 1) * 10, (int) $rowId]);
                }
                flash_set('success', 'Sıra güncellendi');
            }
        }
        admin_redirect('/admin/categories.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, code, label FROM app_categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'Kategori bulunamadı');
            admin_redirect('/admin/categories.php');
        }
        try {
            $inUse = $pdo->prepare('SELECT COUNT(*) FROM file_documents WHERE category = ?');
            $inUse->execute([(string) $row['code']]);
            $used = (int) $inUse->fetchColumn();
            if ($used > 0) {
                $pdo->prepare('UPDATE app_categories SET is_active = 0 WHERE id = ?')->execute([$id]);
                flash_set(
                    'error',
                    $row['label'] . ' silinemedi: ' . $used . ' evrak bu kategoride. Yükleme listesinden kaldırıldı (pasif). Tam silmek için evrakları başka kategoriye alın.'
                );
            } else {
                $pdo->prepare('DELETE FROM app_categories WHERE id = ?')->execute([$id]);
                flash_set('success', $row['label'] . ' silindi');
            }
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE app_categories SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash_set('error', 'Silme yapılamadı, kategori pasife alındı.');
        }
        admin_redirect('/admin/categories.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash_set('error', 'CSRF hatası');
    admin_redirect('/admin/categories.php');
}

$flash = flash_take();
$message = ($flash && ($flash['kind'] ?? '') === 'success') ? (string) $flash['message'] : '';
$error = ($flash && ($flash['kind'] ?? '') === 'error') ? (string) $flash['message'] : '';

$rows = $pdo->query('SELECT * FROM app_categories ORDER BY sort_order, id')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}
$isEdit = $edit !== null;
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
    <h1>Evrak Kategorileri</h1>
    <div class="page-header-actions">
        <a href="/admin/cover_form.php" class="btn btn-ghost btn-sm">Kapak formu</a>
        <a href="/admin/categories.php" class="btn btn-primary btn-sm">+ Yeni Kategori</a>
        <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
    </div>
</div>

<p class="dash-sub" style="margin-bottom:1rem">
    Yükleme ekranındaki evrak türlerini ekleyin, sıralayın ve kapak formundaki kutuya bağlayın.
</p>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card" id="catSaveForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$edit['id'] : 0 ?>">
        <h2><?= $isEdit ? 'Kategori Düzenle' : 'Yeni Kategori Ekle' ?></h2>

        <div class="form-group">
            <label>Ad</label>
            <input class="form-input" name="label" required maxlength="100"
                   value="<?= e($edit['label'] ?? '') ?>" placeholder="Örn: Hasar Fotoğrafı">
        </div>

        <div class="form-group">
            <label>Kod <?= $isEdit ? '' : '(opsiyonel)' ?></label>
            <?php if ($isEdit): ?>
            <input class="form-input" value="<?= e($edit['code']) ?>" readonly disabled>
            <p class="form-hint">Kod yüklü evraklara bağlıdır; değiştirilemez.</p>
            <?php else: ?>
            <input class="form-input" name="code" placeholder="Boş bırakırsanız otomatik" value="">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Kısa açıklama</label>
            <textarea class="form-input" name="description" rows="2" maxlength="255"
                      placeholder="Kategori kartının altında görünecek kısa not"><?= e($edit['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Kapak formunda işaretle</label>
            <select class="form-input" name="form_field_code">
                <option value="">— Bağlı değil —</option>
                <?php
                $selectedField = (string) ($edit['form_field_code'] ?? '');
                foreach ($checkOptions as $fCode => $fLabel):
                ?>
                <option value="<?= e($fCode) ?>" <?= $selectedField === $fCode ? 'selected' : '' ?>>
                    <?= e($fLabel) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Bu kategoriye evrak yüklenince formdaki kutu işaretlenir. Yeni kutu eklemek için Kapak formu sayfasını kullanın.</p>
        </div>

        <div class="form-group">
            <label>Sıra (küçük sayı önce)</label>
            <input class="form-input" type="number" name="sort_order"
                   value="<?= (int)($edit['sort_order'] ?? $nextSort) ?>">
        </div>

        <label class="check-row">
            <input type="checkbox" name="is_required" <?= !empty($edit['is_required']) ? 'checked' : '' ?>>
            Zorunlu evrak
        </label>
        <label class="check-row">
            <input type="checkbox" name="is_active" <?= !$isEdit || !empty($edit['is_active']) ? 'checked' : '' ?>>
            Aktif (yükleme listesinde görünsün)
        </label>

        <button class="btn btn-primary btn-block" type="submit">
            <?= $isEdit ? 'Güncelle' : 'Kategori Ekle' ?>
        </button>
        <?php if ($isEdit): ?>
        <a class="btn btn-ghost btn-block" href="/admin/categories.php">İptal / Yeni kategori</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead>
            <tr>
                <th>Sıra</th>
                <th>Kategori</th>
                <th>Form kutusu</th>
                <th>Kod</th>
                <th>Aktif</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr class="<?= empty($r['is_active']) ? 'row-inactive' : '' ?>">
                <td>
                    <div class="sort-controls">
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="dir" value="up">
                            <button class="btn btn-sm btn-ghost sort-btn" type="submit"
                                    <?= $i === 0 ? 'disabled' : '' ?> title="Yukarı">↑</button>
                        </form>
                        <span class="sort-num"><?= (int)$r['sort_order'] ?></span>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="dir" value="down">
                            <button class="btn btn-sm btn-ghost sort-btn" type="submit"
                                    <?= $i === count($rows) - 1 ? 'disabled' : '' ?> title="Aşağı">↓</button>
                        </form>
                    </div>
                </td>
                <td>
                    <?= e($r['label']) ?>
                    <?php if (!empty($r['description'])): ?>
                    <div class="cat-desc-cell"><?= e($r['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e($checkOptions[$r['form_field_code'] ?? ''] ?? '—') ?></td>
                <td><code><?= e($r['code']) ?></code></td>
                <td><?= !empty($r['is_active']) ? 'Evet' : 'Hayır' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-primary" href="?edit=<?= (int)$r['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Bu kategori silinsin mi? Yüklü evrak varsa silinmez, pasife alınır.')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty-state">Henüz kategori yok. Soldan ekleyin.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

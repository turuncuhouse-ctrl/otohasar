<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Kapak Formu';
$activeNav = 'admin';
$pdo = db();
$sections = cover_form_sections();
$kinds = cover_form_kinds();
$sources = cover_form_data_sources();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $kind = $_POST['kind'] ?? 'text';
        $section = $_POST['section'] ?? 'checks_left';
        $dataKey = trim($_POST['data_key'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $code = trim($_POST['code'] ?? '');

        if (!isset($kinds[$kind])) {
            $kind = 'text';
        }
        if (!isset($sections[$section])) {
            $section = 'checks_left';
        }
        if ($dataKey !== '' && !array_key_exists($dataKey, $sources)) {
            $dataKey = '';
        }

        if ($label === '') {
            flash_set('error', 'Alan adı zorunlu');
            admin_redirect('/admin/cover_form.php' . ($id > 0 ? ('?edit=' . $id) : ''));
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT id FROM cover_form_fields WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() === false) {
                    flash_set('error', 'Alan bulunamadı');
                    admin_redirect('/admin/cover_form.php');
                }
                $pdo->prepare(
                    'UPDATE cover_form_fields SET label=?, kind=?, section=?, data_key=?, sort_order=?, is_active=? WHERE id=?'
                )->execute([
                    $label,
                    $kind,
                    $section,
                    $dataKey !== '' ? $dataKey : null,
                    $sort,
                    $active,
                    $id,
                ]);
                $codeStmt = $pdo->prepare('SELECT code FROM cover_form_fields WHERE id = ?');
                $codeStmt->execute([$id]);
                $savedCode = (string) $codeStmt->fetchColumn();
                if ($kind === 'check' && $savedCode !== '') {
                    set_form_field_categories($pdo, $savedCode, posted_category_codes());
                }
                flash_set('success', 'Form alanı güncellendi');
                admin_redirect('/admin/cover_form.php?edit=' . $id);
            }

            $code = $code !== '' ? slugify_code($code) : slugify_code($label);
            if ($kind === 'check' && !str_starts_with($code, 'chk_')) {
                $code = 'chk_' . $code;
            } elseif ($kind !== 'check' && !str_starts_with($code, 'fld_')) {
                $code = 'fld_' . $code;
            }
            $pdo->prepare(
                'INSERT INTO cover_form_fields (code, kind, section, label, data_key, sort_order, is_active)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $code,
                $kind,
                $section,
                $label,
                $dataKey !== '' ? $dataKey : null,
                $sort,
                $active,
            ]);
            if ($kind === 'check') {
                set_form_field_categories($pdo, $code, posted_category_codes());
            }
            flash_set('success', 'Yeni form alanı eklendi');
            admin_redirect('/admin/cover_form.php');
        } catch (Throwable $e) {
            flash_set('error', 'Kayıt hatası (kod benzersiz olmalı)');
            admin_redirect('/admin/cover_form.php' . ($id > 0 ? ('?edit=' . $id) : ''));
        }
    } elseif ($action === 'move') {
        $id = (int) ($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $stmt = $pdo->prepare('SELECT section FROM cover_form_fields WHERE id = ?');
        $stmt->execute([$id]);
        $section = $stmt->fetchColumn();
        if ($section) {
            $ordered = $pdo->prepare('SELECT id FROM cover_form_fields WHERE section = ? ORDER BY sort_order, id');
            $ordered->execute([$section]);
            $ids = $ordered->fetchAll(PDO::FETCH_COLUMN);
            $idx = array_search((string) $id, array_map('strval', $ids), true);
            if ($idx !== false) {
                $swapWith = $dir === 'up' ? $idx - 1 : ($dir === 'down' ? $idx + 1 : null);
                if ($swapWith !== null && isset($ids[$swapWith])) {
                    $tmp = $ids[$idx];
                    $ids[$idx] = $ids[$swapWith];
                    $ids[$swapWith] = $tmp;
                    $upd = $pdo->prepare('UPDATE cover_form_fields SET sort_order=? WHERE id=?');
                    foreach ($ids as $i => $rowId) {
                        $upd->execute([($i + 1) * 10, (int) $rowId]);
                    }
                    flash_set('success', 'Sıra güncellendi');
                }
            }
        }
        admin_redirect('/admin/cover_form.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, code, label FROM cover_form_fields WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'Alan bulunamadı');
            admin_redirect('/admin/cover_form.php');
        }
        try {
            $pdo->prepare(
                'UPDATE app_categories SET form_field_code = NULL WHERE form_field_code = ?'
            )->execute([(string) $row['code']]);
            $pdo->prepare('DELETE FROM cover_form_fields WHERE id = ?')->execute([$id]);
            flash_set('success', $row['label'] . ' formdan silindi');
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE cover_form_fields SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash_set('error', 'Silinemedi, alan pasife alındı.');
        }
        admin_redirect('/admin/cover_form.php');
    } elseif ($action === 'map_cats') {
        $fieldCode = trim($_POST['field_code'] ?? '');
        $codes = posted_category_codes();
        $stmt = $pdo->prepare('SELECT code, label, kind FROM cover_form_fields WHERE code = ?');
        $stmt->execute([$fieldCode]);
        $field = $stmt->fetch();
        if (!$field || ($field['kind'] ?? '') !== 'check') {
            flash_set('error', 'Onay kutusu bulunamadı');
            admin_redirect('/admin/cover_form.php');
        }
        set_form_field_categories($pdo, $fieldCode, $codes);
        flash_set('success', $field['label'] . ' evrak kategorisine bağlandı');
        admin_redirect('/admin/cover_form.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash_set('error', 'CSRF hatası');
    admin_redirect('/admin/cover_form.php');
}

$flash = flash_take();
$message = ($flash && ($flash['kind'] ?? '') === 'success') ? (string) $flash['message'] : '';
$error = ($flash && ($flash['kind'] ?? '') === 'error') ? (string) $flash['message'] : '';

$rows = cover_form_fields(false);
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}
$isEdit = $edit !== null;
$showPreview = isset($_GET['preview']);
$catOptions = all_category_options();
$catsByField = cover_form_category_map();

$nextSort = 10;
if (!$isEdit) {
    $sec = $_GET['section'] ?? 'checks_left';
    if (!isset($sections[$sec])) {
        $sec = 'checks_left';
    }
    $maxSort = 0;
    foreach ($rows as $r) {
        if (($r['section'] ?? '') === $sec) {
            $maxSort = max($maxSort, (int) $r['sort_order']);
        }
    }
    $nextSort = $maxSort + 10;
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Kapak Formu</h1>
    <div class="page-header-actions">
        <a href="/admin/cover_form.php?preview=1" class="btn btn-ghost btn-sm">Önizleme</a>
        <a href="/admin/cover_form.php" class="btn btn-primary btn-sm">+ Yeni alan</a>
        <a href="/admin/categories.php" class="btn btn-ghost btn-sm">Kategoriler</a>
        <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
    </div>
</div>

<p class="dash-sub" style="margin-bottom:1rem">
    Yazdırılan kapak formuna alan ekleyin, çıkarın veya sırasını değiştirin.
    Onay kutularını evrak kategorilerine <a href="/admin/categories.php">Kategoriler</a> sayfasından bağlayın.
</p>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($showPreview): ?>
<div class="admin-table-wrap" style="margin-bottom:1rem">
    <h2 style="margin-bottom:.75rem">Boş form önizleme</h2>
    <?php
    $file = [];
    $uploaded = [];
    $grouped = cover_form_grouped(true);
    $catsByField = cover_form_category_map();
    require __DIR__ . '/../../includes/cover_form_sheet.php';
    ?>
</div>
<?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$edit['id'] : 0 ?>">
        <h2><?= $isEdit ? 'Alanı düzenle' : 'Forma yeni alan ekle' ?></h2>

        <div class="form-group">
            <label>Formdaki yazı</label>
            <input class="form-input" name="label" required maxlength="200"
                   value="<?= e($edit['label'] ?? '') ?>" placeholder="Örn: EKSPERTİZ RAPORU">
        </div>

        <div class="form-group">
            <label>Tür</label>
            <select class="form-input" name="kind">
                <?php $kindSel = $edit['kind'] ?? 'check';
                foreach ($kinds as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= $kindSel === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Bölüm</label>
            <select class="form-input" name="section">
                <?php $secSel = $edit['section'] ?? ($_GET['section'] ?? 'checks_left');
                foreach ($sections as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= $secSel === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Dosyadan doldur</label>
            <select class="form-input" name="data_key">
                <?php $srcSel = (string) ($edit['data_key'] ?? '');
                foreach ($sources as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= $srcSel === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Onay kutusu için “Araç müşterideyse işaretle” seçilebilir. Evrakla işaretleme aşağıdaki kategorilerden gelir.</p>
        </div>

        <div class="form-group">
            <label>Evrak yükleme kategorisi</label>
            <select class="form-input map-select" name="category_codes[]" multiple size="8">
                <?= html_category_map_options($isEdit ? ($catsByField[$edit['code']] ?? []) : []) ?>
            </select>
            <p class="form-hint">Sol evrak kutuları (RAPOR ASLI, RUHSAT…) için yükleme kategorisini buradan veya sağ listedeki eşleştirme kutusundan seçin. Ctrl ile birden fazla seçilir.</p>
        </div>

        <?php if (!$isEdit): ?>
        <div class="form-group">
            <label>Kod (opsiyonel)</label>
            <input class="form-input" name="code" placeholder="Boş bırakırsanız otomatik">
        </div>
        <?php else: ?>
        <div class="form-group">
            <label>Kod</label>
            <input class="form-input" value="<?= e($edit['code']) ?>" readonly disabled>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Sıra</label>
            <input class="form-input" type="number" name="sort_order"
                   value="<?= (int)($edit['sort_order'] ?? $nextSort) ?>">
        </div>

        <label class="check-row">
            <input type="checkbox" name="is_active" <?= !$isEdit || !empty($edit['is_active']) ? 'checked' : '' ?>>
            Aktif (formda görünsün)
        </label>

        <button class="btn btn-primary btn-block" type="submit">
            <?= $isEdit ? 'Güncelle' : 'Alan Ekle' ?>
        </button>
        <?php if ($isEdit): ?>
        <a class="btn btn-ghost btn-block" href="/admin/cover_form.php">İptal / Yeni alan</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <?php
        $bySection = [];
        foreach ($rows as $r) {
            $bySection[$r['section']][] = $r;
        }
        foreach ($sections as $secKey => $secLabel):
            $list = $bySection[$secKey] ?? [];
        ?>
        <h2 style="font-size:1rem;margin:1rem 0 .5rem"><?= e($secLabel) ?></h2>
        <table class="report-table">
            <thead>
            <tr>
                <th>Sıra</th>
                <th>Alan</th>
                <th>Evrak kategorisi</th>
                <th>Aktif</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $i => $r): ?>
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
                                    <?= $i === count($list) - 1 ? 'disabled' : '' ?> title="Aşağı">↓</button>
                        </form>
                    </div>
                </td>
                <td><?= e($r['label']) ?><div class="cat-desc-cell"><?= e($kinds[$r['kind']] ?? $r['kind']) ?></div></td>
                <td class="map-cell">
                    <?php if (($r['kind'] ?? '') === 'check'): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="map_cats">
                        <input type="hidden" name="field_code" value="<?= e($r['code']) ?>">
                        <select class="form-input map-select" name="category_codes[]" multiple size="5">
                            <?= html_category_map_options($catsByField[$r['code']] ?? []) ?>
                        </select>
                        <button class="btn btn-sm btn-primary" type="submit" style="margin-top:.35rem">Eşleştir</button>
                    </form>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= !empty($r['is_active']) ? 'Evet' : 'Hayır' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-primary" href="?edit=<?= (int)$r['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Bu alan formdan silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?>
            <tr><td colspan="5" class="empty-state">Bu bölümde alan yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

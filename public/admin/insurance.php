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
$formTypes = insurance_form_doc_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $labor = (float) str_replace(',', '.', (string) ($_POST['labor_discount'] ?? '0'));
        $parts = (float) str_replace(',', '.', (string) ($_POST['parts_discount'] ?? '0'));
        $note = trim($_POST['note'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($labor < 0) {
            $labor = 0;
        }
        if ($labor > 100) {
            $labor = 100;
        }
        if ($parts < 0) {
            $parts = 0;
        }
        if ($parts > 100) {
            $parts = 100;
        }
        if ($name === '') {
            $error = 'Şirket adı zorunlu';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare(
                        'UPDATE insurance_companies SET name=?, labor_discount=?, parts_discount=?, note=?, is_active=? WHERE id=?'
                    )->execute([$name, $labor, $parts, $note !== '' ? $note : null, $active, $id]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO insurance_companies (name, labor_discount, parts_discount, note, is_active) VALUES (?,?,?,?,?)'
                    )->execute([$name, $labor, $parts, $note !== '' ? $note : null, $active]);
                    $id = (int) $pdo->lastInsertId();
                }
                header('Location: /admin/insurance.php?edit=' . $id . '&ok=1');
                exit;
            } catch (Throwable $e) {
                $error = 'Kayıt hatası (şirket adı benzersiz olmalı)';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM insurance_companies WHERE id=?')->execute([$id]);
            $message = 'Şirket silindi';
        } catch (Throwable $e) {
            $error = 'Silinemedi';
        }
    } elseif ($action === 'save_template') {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        $docType = (string) ($_POST['doc_type'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($companyId <= 0 || !isset($formTypes[$docType])) {
            $error = 'Geçersiz şablon bilgisi';
        } elseif (empty($_FILES['template_file']['name'])) {
            $error = 'PDF şablon dosyası seçin';
        } else {
            $company = find_insurance_company_by_id($companyId);
            if (!$company) {
                $error = 'Şirket bulunamadı';
            } else {
                $item = normalize_uploaded_files($_FILES['template_file'])[0] ?? null;
                if (!$item || $item['error'] !== UPLOAD_ERR_OK) {
                    $error = upload_error_message((int) ($item['error'] ?? UPLOAD_ERR_NO_FILE), (string) ($item['name'] ?? ''));
                } elseif ($item['size'] > app_config()['app']['upload_max']) {
                    $error = 'Dosya 20MB limitini aşıyor';
                } else {
                    $validated = validate_document_mime($item['tmp_name'], $item['name']);
                    if (!$validated) {
                        $error = 'Geçersiz dosya türü (PDF önerilir; JPEG/PNG de kabul edilir)';
                    } else {
                        $dir = template_storage_dir($companyId);
                        $filename = random_filename($validated['ext']);
                        $dest = $dir . '/' . $filename;
                        if (!move_uploaded_file($item['tmp_name'], $dest)) {
                            $error = 'Şablon kaydedilemedi';
                        } else {
                            $rel = 'templates/' . $companyId . '/' . $filename;
                            $title = $title !== '' ? $title : $formTypes[$docType];
                            try {
                                $existing = $pdo->prepare(
                                    'SELECT id, file_path FROM insurance_doc_templates
                                     WHERE insurance_company_id = ? AND doc_type = ? LIMIT 1'
                                );
                                $existing->execute([$companyId, $docType]);
                                $old = $existing->fetch();
                                if ($old) {
                                    $pdo->prepare(
                                        'UPDATE insurance_doc_templates
                                         SET title=?, file_path=?, original_name=?, mime_type=?, is_active=1
                                         WHERE id=?'
                                    )->execute([$title, $rel, $item['name'], $validated['mime'], (int) $old['id']]);
                                    $oldAbs = rtrim((string) app_config()['paths']['uploads'], '/\\') . '/' . $old['file_path'];
                                    if (is_file($oldAbs) && realpath($oldAbs) !== realpath($dest)) {
                                        @unlink($oldAbs);
                                    }
                                } else {
                                    $pdo->prepare(
                                        'INSERT INTO insurance_doc_templates
                                         (insurance_company_id, doc_type, title, file_path, original_name, mime_type, is_active)
                                         VALUES (?,?,?,?,?,?,1)'
                                    )->execute([$companyId, $docType, $title, $rel, $item['name'], $validated['mime']]);
                                }
                                header('Location: /admin/insurance.php?edit=' . $companyId . '&ok=1');
                                exit;
                            } catch (Throwable $e) {
                                @unlink($dest);
                                $error = 'Şablon kaydedilemedi. Veritabanı migrasyonu (v6) çalışmış mı?';
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === 'delete_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $companyId = (int) ($_POST['company_id'] ?? 0);
        $tpl = find_insurance_template($templateId);
        if ($tpl && (int) $tpl['insurance_company_id'] === $companyId) {
            $pdo->prepare('DELETE FROM insurance_doc_templates WHERE id=?')->execute([$templateId]);
            $abs = rtrim((string) app_config()['paths']['uploads'], '/\\') . '/' . $tpl['file_path'];
            if (is_file($abs)) {
                @unlink($abs);
            }
            header('Location: /admin/insurance.php?edit=' . $companyId . '&ok=1');
            exit;
        }
        $error = 'Şablon silinemedi';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'CSRF hatası';
}

if (isset($_GET['ok'])) {
    $message = 'Kayıt güncellendi';
}

$rows = $pdo->query('SELECT * FROM insurance_companies ORDER BY name')->fetchAll();
$templateCounts = [];
try {
    $cntRows = $pdo->query(
        'SELECT insurance_company_id, COUNT(*) AS c
         FROM insurance_doc_templates WHERE is_active = 1
         GROUP BY insurance_company_id'
    )->fetchAll();
    foreach ($cntRows as $cr) {
        $templateCounts[(int) $cr['insurance_company_id']] = (int) $cr['c'];
    }
} catch (Throwable $e) {
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}
$isEdit = $edit !== null;
$editTemplates = $isEdit ? insurance_templates_for_company((int) $edit['id'], false) : [];
$editByType = [];
foreach ($editTemplates as $t) {
    $editByType[$t['doc_type']] = $t;
}
$formTypeCount = count($formTypes);

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Anlaşmalı Sigorta Şirketleri</h1>
    <div class="page-header-actions">
        <a href="/admin/insurance.php" class="btn btn-primary btn-sm">+ Yeni Şirket</a>
        <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
    </div>
</div>

<p class="dash-sub" style="margin-bottom:1rem">
    Anlaşmalı kasko şirketlerini ekleyin; işçilik / parça iskontosu girin.
    Her şirket için Taahhüt, Teslim, İbra ve Temlik PDF şablonlarını yükleyin —
    müşteri portalında yalnızca dosyadaki sigorta şirketine ait formlar görünür.
</p>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card" id="companyForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$edit['id'] : 0 ?>">
        <h2><?= $isEdit ? 'Şirket Düzenle' : 'Yeni Anlaşmalı Şirket' ?></h2>

        <div class="form-group">
            <label>Şirket adı</label>
            <input class="form-input" name="name" required maxlength="120"
                   value="<?= e($edit['name'] ?? '') ?>" placeholder="Örn: Anadolu Sigorta">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>İşçilik iskontosu %</label>
                <input class="form-input" type="number" step="0.01" min="0" max="100"
                       name="labor_discount" value="<?= e((string)($edit['labor_discount'] ?? '0')) ?>">
            </div>
            <div class="form-group">
                <label>Parça iskontosu %</label>
                <input class="form-input" type="number" step="0.01" min="0" max="100"
                       name="parts_discount" value="<?= e((string)($edit['parts_discount'] ?? '0')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Not (opsiyonel)</label>
            <input class="form-input" name="note" value="<?= e($edit['note'] ?? '') ?>"
                   placeholder="İç not">
        </div>
        <label class="check-row">
            <input type="checkbox" name="is_active" <?= !$isEdit || !empty($edit['is_active']) ? 'checked' : '' ?>>
            Aktif / Anlaşmalı (yeni dosyada seçilebilir)
        </label>
        <button class="btn btn-primary btn-block" type="submit">
            <?= $isEdit ? 'Şirketi Güncelle' : 'Şirket Ekle' ?>
        </button>
        <?php if ($isEdit): ?>
        <a class="btn btn-ghost btn-block" href="/admin/insurance.php">İptal / Yeni şirket</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead>
            <tr>
                <th>Şirket</th>
                <th>İşçilik %</th>
                <th>Parça %</th>
                <th>Formlar</th>
                <th>Aktif</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $tid = (int) $r['id'];
                $tc = $templateCounts[$tid] ?? 0;
            ?>
            <tr class="<?= empty($r['is_active']) ? 'row-inactive' : '' ?>">
                <td>
                    <?= e($r['name']) ?>
                    <?php if (!empty($r['note'])): ?>
                    <br><small class="text-muted"><?= e($r['note']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= e(rtrim(rtrim(number_format((float)$r['labor_discount'], 2, '.', ''), '0'), '.') ?: '0') ?></td>
                <td><?= e(rtrim(rtrim(number_format((float)$r['parts_discount'], 2, '.', ''), '0'), '.') ?: '0') ?></td>
                <td>
                    <span class="tpl-count <?= $tc >= $formTypeCount ? 'tpl-count-ok' : ($tc > 0 ? 'tpl-count-partial' : 'tpl-count-empty') ?>">
                        <?= $tc ?>/<?= $formTypeCount ?>
                    </span>
                </td>
                <td><?= !empty($r['is_active']) ? 'Evet' : 'Hayır' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-primary" href="?edit=<?= $tid ?>">Düzenle / PDF</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Şirket ve şablonları silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $tid ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty-state">Henüz şirket yok. Soldan ekleyin.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isEdit): ?>
<div class="admin-form-card ins-templates-panel">
    <h2><?= e($edit['name']) ?> — Form PDF Şablonları</h2>
    <p class="form-hint">
        Müşteri eksik evrak durumunda bu şirketin şablonlarını indirip imzalayarak geri yükler.
        Her form tipi için bir PDF yükleyin (görsel de kabul edilir).
    </p>

    <?php foreach ($formTypes as $type => $label):
        $tpl = $editByType[$type] ?? null;
    ?>
    <div class="ins-admin-template-row <?= $tpl ? 'has-tpl' : 'no-tpl' ?>">
        <div class="ins-admin-template-head">
            <h3><?= e($label) ?></h3>
            <?php if ($tpl): ?>
            <span class="tpl-badge tpl-badge-ok">Yüklü</span>
            <?php else: ?>
            <span class="tpl-badge tpl-badge-miss">Eksik</span>
            <?php endif; ?>
        </div>

        <?php if ($tpl): ?>
        <p class="ins-tpl-meta">
            <strong><?= e($tpl['title']) ?></strong>
            · <?= e($tpl['original_name']) ?>
            · <?= e($tpl['mime_type']) ?>
        </p>
        <div class="ins-template-actions">
            <a class="btn btn-sm btn-secondary"
               href="/admin/template_download.php?id=<?= (int)$tpl['id'] ?>">Önizle / İndir</a>
            <form method="post" class="inline-form" onsubmit="return confirm('Bu şablon silinsin mi?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="company_id" value="<?= (int)$edit['id'] ?>">
                <input type="hidden" name="template_id" value="<?= (int)$tpl['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Şablonu sil</button>
            </form>
        </div>
        <?php else: ?>
        <p class="text-muted">Henüz şablon yok — PDF yükleyin.</p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="ins-admin-upload-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="company_id" value="<?= (int)$edit['id'] ?>">
            <input type="hidden" name="doc_type" value="<?= e($type) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Başlık</label>
                    <input class="form-input" name="title" value="<?= e($tpl['title'] ?? $label) ?>">
                </div>
                <div class="form-group">
                    <label>PDF dosyası</label>
                    <input class="form-input" type="file" name="template_file"
                           accept=".pdf,application/pdf,image/jpeg,image/png,image/webp" required>
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" type="submit">
                <?= $tpl ? 'PDF’yi değiştir' : 'PDF yükle' ?>
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

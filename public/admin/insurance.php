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
                    $id = (int) $pdo->lastInsertId();
                }
                $message = 'Sigorta şirketi kaydedildi';
                if ($id > 0) {
                    header('Location: /admin/insurance.php?edit=' . $id . '&ok=1');
                    exit;
                }
            } catch (Throwable $e) {
                $error = 'Kayıt hatası (isim benzersiz olmalı)';
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM insurance_companies WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        $message = 'Silindi';
    } elseif ($action === 'save_template') {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        $docType = (string) ($_POST['doc_type'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($companyId <= 0 || !isset($formTypes[$docType])) {
            $error = 'Geçersiz şablon bilgisi';
        } elseif (empty($_FILES['template_file']['name'])) {
            $error = 'Şablon dosyası seçin (PDF veya görsel)';
        } else {
            $company = null;
            foreach (insurance_companies(false) as $c) {
                if ((int) $c['id'] === $companyId) {
                    $company = $c;
                    break;
                }
            }
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
                        $error = 'Geçersiz dosya türü (PDF, JPEG, PNG, WebP)';
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
                                    if (is_file($oldAbs)) {
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
                                $error = 'Şablon veritabanına yazılamadı (migrate_v6 çalıştırılmış mı?)';
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
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}
$editTemplates = $edit ? insurance_templates_for_company((int) $edit['id'], false) : [];
$editByType = [];
foreach ($editTemplates as $t) {
    $editByType[$t['doc_type']] = $t;
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

<?php if ($edit): ?>
<div class="admin-form-card" style="margin-top:1.5rem;max-width:900px">
    <h2><?= e($edit['name']) ?> — Form Şablonları</h2>
    <p class="text-muted">Müşteri portalında yalnızca bu şirketin Taahhüt / Teslim / İbra şablonları görünür.</p>
    <?php foreach ($formTypes as $type => $label):
        $tpl = $editByType[$type] ?? null;
    ?>
    <div class="ins-admin-template-row">
        <h3><?= e($label) ?></h3>
        <?php if ($tpl): ?>
        <p>Mevcut: <strong><?= e($tpl['original_name']) ?></strong> · <?= e($tpl['title']) ?></p>
        <form method="post" class="inline-form" onsubmit="return confirm('Şablon silinsin mi?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="company_id" value="<?= (int)$edit['id'] ?>">
            <input type="hidden" name="template_id" value="<?= (int)$tpl['id'] ?>">
            <button class="btn btn-sm btn-ghost" type="submit">Şablonu sil</button>
        </form>
        <?php else: ?>
        <p class="text-muted">Henüz şablon yok.</p>
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
                    <label>Dosya (PDF / JPEG / PNG)</label>
                    <input class="form-input" type="file" name="template_file" accept=".pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" type="submit"><?= $tpl ? 'Şablonu değiştir' : 'Şablon yükle' ?></button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

$plate = portal_require_plate();
portal_require_kvkk();
$fileId = (int) ($_GET['id'] ?? portal_file_id() ?? 0);
if ($fileId <= 0) {
    header('Location: /musteri/liste.php');
    exit;
}

$file = find_portal_file($fileId, $plate);
if (!$file) {
    header('Location: /musteri/');
    exit;
}

portal_set_file($fileId, $plate, !empty($_SESSION['portal_via_token']));

$statuses = status_labels();
$categories = customer_upload_categories();
$categoryDescriptions = category_descriptions();
$formTypes = insurance_form_doc_types();
$canUpload = is_customer_upload_granted($file);
$statusLabel = $statuses[$file['status']] ?? $file['status'];
$statusColor = status_colors()[$file['status']] ?? 'status-slate';

$insCompany = find_insurance_company_by_name($file['insurance_company'] ?? null);
$templates = $insCompany ? insurance_templates_for_company((int) $insCompany['id']) : [];
$templatesByType = [];
foreach ($templates as $tpl) {
    $templatesByType[$tpl['doc_type']] = $tpl;
}

$stmt = db()->prepare(
    'SELECT fd.*, COALESCE(u.name, \'Müşteri\') AS uploader_name
     FROM file_documents fd
     LEFT JOIN users u ON u.id = fd.uploaded_by
     WHERE fd.damage_file_id = ?
     ORDER BY fd.uploaded_at DESC'
);
$stmt->execute([$fileId]);
$documents = $stmt->fetchAll();

$pageTitle = $file['file_number'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> — OTOHASAR</title>
    <link rel="stylesheet" href="<?= e(asset_css_url()) ?>">
</head>
<body class="portal-body">
<main class="portal-wrap">
    <div class="portal-card portal-card-wide">
        <div class="portal-top">
            <div>
                <div class="portal-brand-mini">OTOHASAR</div>
                <?= plate_badge_html($file['plate']) ?>
                <h1 class="portal-file-title"><?= e($file['file_number']) ?></h1>
                <p class="portal-sub"><?= e($file['brand'] . ' ' . $file['model']) ?> · <?= e($file['customer_name']) ?></p>
                <?php if (!empty($file['insurance_company'])): ?>
                <p class="portal-sub">Kasko: <?= e($file['insurance_company']) ?></p>
                <?php endif; ?>
            </div>
            <a class="btn btn-ghost btn-sm" href="/musteri/cikis.php">Çıkış</a>
        </div>

        <div class="portal-status-block">
            <span class="status-pill <?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
            <p>Aracınızın güncel süreci yukarıdaki durumdur. Detay için servisinizle iletişime geçebilirsiniz.</p>
        </div>

        <?php if (!empty($file['customer_message'])): ?>
        <div class="portal-customer-message">
            <h3>Servisten mesaj</h3>
            <p><?= e($file['customer_message']) ?></p>
            <?php if (!empty($file['customer_message_at'])): ?>
            <span class="msg-meta"><?= e(date('d.m.Y H:i', strtotime((string)$file['customer_message_at']))) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <section class="ins-template-section">
            <h3>Kasko formları (Taahhüt / Teslim / İbra)</h3>
            <?php if (!$insCompany): ?>
            <p class="empty-state">Bu dosyada anlaşmalı kasko şirketi tanımlı değil.</p>
            <?php elseif (!$templates): ?>
            <p class="empty-state">Bu kasko şirketi için henüz şablon yüklenmemiş. Servisinizle iletişime geçin.</p>
            <?php else: ?>
            <p class="portal-sub">Yalnızca <strong><?= e($insCompany['name']) ?></strong> formlarını indirip imzalayarak geri yükleyebilirsiniz.</p>
            <div class="ins-template-grid">
                <?php foreach ($formTypes as $type => $label):
                    $tpl = $templatesByType[$type] ?? null;
                    if (!$tpl) continue;
                ?>
                <div class="ins-template-card">
                    <strong><?= e($tpl['title'] ?: $label) ?></strong>
                    <span class="text-muted"><?= e($tpl['original_name']) ?></span>
                    <div class="ins-template-actions">
                        <a class="btn btn-secondary btn-sm"
                           href="/api/customer_template_download.php?file_id=<?= (int)$fileId ?>&amp;template_id=<?= (int)$tpl['id'] ?>">
                            İndir
                        </a>
                        <?php if ($canUpload): ?>
                        <label class="btn btn-primary btn-sm upload-picker-btn">
                            İmzalı yükle
                            <input type="file" class="upload-picker-input ins-signed-input"
                                   accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                                   data-category="<?= e($type) ?>">
                        </label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$canUpload): ?>
            <p class="portal-footnote">İmzalı form yüklemek için servisinizin yükleme iznini açması gerekir. Şablonları yine de indirebilirsiniz.</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($canUpload): ?>
        <div class="grant-banner grant-banner-ok">
            Eksik evrak yükleme açık · <?= e(customer_upload_remaining_label($file) ?? '') ?>
            <?php if (!empty($file['customer_upload_note'])): ?>
            <br><strong>İstenen:</strong> <?= e($file['customer_upload_note']) ?>
            <?php endif; ?>
        </div>
        <div class="upload-section">
            <h3>Evrak / fotoğraf yükle</h3>
            <div class="category-grid compact" id="uploadCategoryGrid">
                <?php foreach ($categories as $key => $label):
                    if (isset($formTypes[$key])) continue;
                    $icon = match($key) {
                        'ruhsat' => '📄', 'ehliyet' => '🪪', 'tutanak' => '📋',
                        'hasar_foto' => '📸', 'ekspertiz' => '🔍', 'diger' => '📁',
                        default => '📎'
                    };
                    $accept = 'image/jpeg,image/png,image/webp,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.heic,.heif';
                ?>
                <div class="category-card small" data-category="<?= e($key) ?>">
                    <span class="cat-icon"><?= $icon ?></span>
                    <span class="cat-label"><?= e($label) ?></span>
                    <?php if (!empty($categoryDescriptions[$key])): ?>
                    <span class="cat-desc"><?= e($categoryDescriptions[$key]) ?></span>
                    <?php endif; ?>
                    <input type="file" class="cat-input" multiple accept="<?= e($accept) ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="upload-quick-actions" data-category="hasar_foto">
                <label class="btn btn-secondary btn-sm upload-picker-btn">
                    📷 Kamera ile çek
                    <input type="file" class="upload-picker-input" accept="image/*" capture="environment" data-source="camera">
                </label>
                <label class="btn btn-secondary btn-sm upload-picker-btn">
                    🖼️ Galeriden çoklu seç
                    <input type="file" class="upload-picker-input" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.heic,.heif" multiple data-source="gallery">
                </label>
            </div>
            <div id="uploadPreview" class="upload-preview"></div>
        </div>
        <?php else: ?>
        <div class="grant-banner grant-banner-warn">
            Şu an evrak yükleme kapalı. Eksik belge varsa servisiniz WhatsApp ile yükleme linki gönderecektir.
        </div>
        <div id="uploadPreview" class="upload-preview"></div>
        <?php endif; ?>

        <h3 class="portal-docs-title">Yüklenen evraklar</h3>
        <div class="doc-grid" id="docGrid">
            <?php foreach ($documents as $doc): ?>
            <div class="doc-card">
                <a href="/<?= e($doc['file_path']) ?>" target="_blank" class="doc-thumb">
                    <?php if (str_starts_with((string)$doc['mime_type'], 'image/')): ?>
                    <img src="/<?= e($doc['file_path']) ?>" alt="<?= e($doc['original_name']) ?>" loading="lazy">
                    <?php else: ?>
                    <span class="doc-file-badge">PDF</span>
                    <?php endif; ?>
                </a>
                <div class="doc-info">
                    <span class="doc-cat"><?= e($categories[$doc['category']] ?? category_labels()[$doc['category']] ?? $doc['category']) ?></span>
                    <span class="doc-name"><?= e($doc['original_name']) ?></span>
                    <span class="doc-meta"><?= e($doc['uploader_name']) ?> · <?= date('d.m.Y H:i', strtotime($doc['uploaded_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($documents)): ?>
        <p class="empty-state">Henüz evrak yok.</p>
        <?php endif; ?>
    </div>
</main>
<div id="toastContainer" class="toast-container"></div>
<script src="<?= e(asset_js_url()) ?>"></script>
<script>
(function() {
    var fileId = <?= (int)$fileId ?>;
    var opts = {
        fileId: fileId,
        previewEl: document.getElementById('uploadPreview'),
        uploadUrl: '/api/customer_upload.php',
        reloadOnSuccess: true
    };
    if (document.getElementById('uploadCategoryGrid')) {
        bindCategoryUpload(Object.assign({ gridSelector: '#uploadCategoryGrid' }, opts));
    }
    if (document.querySelector('.upload-quick-actions')) {
        bindUploadPickers(opts);
    }
    document.querySelectorAll('.ins-signed-input').forEach(function(input) {
        input.addEventListener('change', function() {
            startUploadFromInput(this, opts, this.getAttribute('data-category'));
        });
    });
})();
</script>
</body>
</html>

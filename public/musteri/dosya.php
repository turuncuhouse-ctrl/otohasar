<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

$plate = portal_require_plate();
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
$canUpload = is_customer_upload_granted($file);
$statusLabel = $statuses[$file['status']] ?? $file['status'];
$statusColor = status_colors()[$file['status']] ?? 'status-slate';

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
    <link rel="stylesheet" href="/assets/css/style.css">
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
            </div>
            <a class="btn btn-ghost btn-sm" href="/musteri/cikis.php">Çıkış</a>
        </div>

        <div class="portal-status-block">
            <span class="status-pill <?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
            <p>Aracınızın güncel süreci yukarıdaki durumdur. Detay için servisinizle iletişime geçebilirsiniz.</p>
        </div>

        <?php if ($canUpload): ?>
        <div class="grant-banner grant-banner-ok">
            Eksik evrak yükleme açık · <?= e(customer_upload_remaining_label($file) ?? '') ?>
            <?php if (!empty($file['customer_upload_note'])): ?>
            <br><strong>İstenen:</strong> <?= e($file['customer_upload_note']) ?>
            <?php endif; ?>
        </div>
        <div class="upload-section">
            <h3>Evrak / fotoğraf yükle</h3>
            <div class="category-grid compact">
                <?php foreach ($categories as $key => $label):
                    $icon = match($key) {
                        'ruhsat' => '📄', 'ehliyet' => '🪪', 'tutanak' => '📋',
                        'hasar_foto' => '📸', 'ekspertiz' => '🔍', 'diger' => '📁',
                        default => '📎'
                    };
                ?>
                <div class="category-card small" data-category="<?= e($key) ?>">
                    <span class="cat-icon"><?= $icon ?></span>
                    <span class="cat-label"><?= e($label) ?></span>
                    <input type="file" class="cat-input" accept="image/jpeg,image/png,image/webp" capture="environment"
                           <?= $key === 'hasar_foto' ? 'multiple' : '' ?>>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="grant-banner grant-banner-warn">
            Şu an evrak yükleme kapalı. Eksik belge varsa servisiniz WhatsApp ile yükleme linki gönderecektir.
        </div>
        <?php endif; ?>

        <h3 class="portal-docs-title">Yüklenen evraklar</h3>
        <div class="doc-grid" id="docGrid">
            <?php foreach ($documents as $doc): ?>
            <div class="doc-card">
                <a href="/<?= e($doc['file_path']) ?>" target="_blank" class="doc-thumb">
                    <img src="/<?= e($doc['file_path']) ?>" alt="<?= e($doc['original_name']) ?>" loading="lazy">
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
<script src="/assets/js/app.js"></script>
<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var fileId = <?= (int)$fileId ?>;

    function uploadFiles(category, files) {
        var formData = new FormData();
        formData.append('csrf', csrf);
        formData.append('damage_file_id', fileId);
        formData.append('category', category);
        for (var i = 0; i < files.length; i++) formData.append('files[]', files[i]);
        fetch('/api/customer_upload.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    showToast((data.uploaded || []).length + ' dosya yüklendi', 'success');
                    location.reload();
                } else {
                    showToast(data.error || 'Yükleme hatası', 'error');
                }
            })
            .catch(function() { showToast('Bağlantı hatası', 'error'); });
    }

    document.querySelectorAll('.category-card').forEach(function(card) {
        var input = card.querySelector('.cat-input');
        if (!input) return;
        card.addEventListener('click', function(e) { if (e.target !== input) input.click(); });
        input.addEventListener('change', function() {
            if (this.files.length) uploadFiles(card.dataset.category, this.files);
            this.value = '';
        });
    });
})();
</script>
</body>
</html>

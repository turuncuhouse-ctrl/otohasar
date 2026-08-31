<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
$fileId = (int) ($_GET['id'] ?? 0);

if ($fileId <= 0) {
    header('Location: /dashboard.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.plate, v.brand, v.model, v.year, v.color, v.chassis_no,
            c.name AS customer_name, c.phone AS customer_phone, c.tc_vkn, c.email AS customer_email,
            u.name AS advisor_name
     FROM damage_files df
     JOIN vehicles v ON v.id = df.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     JOIN users u ON u.id = df.advisor_id
     WHERE df.id = ?'
);
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file || !can_access_file($currentUser, $file)) {
    http_response_code(403);
    echo 'Erişim reddedildi';
    exit;
}

$permissions = get_file_permissions($currentUser, $file);

$stmt = $pdo->prepare(
    'SELECT fd.*, u.name AS uploader_name FROM file_documents fd
     JOIN users u ON u.id = fd.uploaded_by WHERE fd.damage_file_id = ? ORDER BY fd.uploaded_at DESC'
);
$stmt->execute([$fileId]);
$documents = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT fl.*, u.name AS user_name FROM file_logs fl
     JOIN users u ON u.id = fl.user_id WHERE fl.damage_file_id = ? ORDER BY fl.created_at DESC'
);
$stmt->execute([$fileId]);
$logs = $stmt->fetchAll();

$pageTitle = $file['file_number'];
$activeNav = 'dashboard';
$categories = category_labels();
$statuses = status_labels();

require __DIR__ . '/../includes/header.php';
?>

<div class="file-detail">
    <div class="file-header">
        <div class="file-header-left">
            <?= plate_badge_html($file['plate']) ?>
            <h1><?= e($file['file_number']) ?></h1>
            <span class="status-pill <?= e(status_colors()[$file['status']]) ?>"><?= e($statuses[$file['status']]) ?></span>
        </div>
        <div class="file-header-right">
            <?php if ($permissions['can_change_status']): ?>
            <select id="statusSelect" class="form-input status-select">
                <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $file['status'] === $key ? 'selected' : '' ?>
                    <?= !in_array($key, $permissions['allowed_statuses'], true) ? 'disabled' : '' ?>>
                    <?= e($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php
            $waUrl = wa_url(
                $file['customer_phone'] ?? null,
                wa_status_message($file['customer_name'], $file['plate'], $file['file_number'], $file['status'])
            );
            if ($waUrl):
            ?>
            <a class="btn btn-wa btn-wa-lg" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"
               data-file-id="<?= (int)$fileId ?>" data-status="<?= e($file['status']) ?>">WhatsApp ile bildir</a>
            <?php elseif ($currentUser['role'] !== 'workshop'): ?>
            <span class="wa-missing">Müşteri telefonu yok — WhatsApp gönderilemez</span>
            <?php endif; ?>
            <a class="btn btn-primary" href="/api/download_zip.php?file_id=<?= (int)$fileId ?>">Evrakları ZIP İndir</a>
            <a class="btn btn-ghost" href="/api/download_zip.php?plate=<?= urlencode($file['plate']) ?>">Plaka klasör ZIP</a>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" data-tab="docs">Evraklar</button>
        <button class="tab" data-tab="timeline">Zaman Çizelgesi</button>
        <button class="tab" data-tab="info">Dosya Bilgisi</button>
    </div>

    <div class="tab-content active" id="tab-docs">
        <?php if ($permissions['can_upload']): ?>
        <div class="upload-section">
            <h3>Evrak Yükle</h3>
            <div class="category-grid compact">
                <?php foreach ($categories as $key => $label):
                    if (!in_array($key, $permissions['allowed_categories'], true)) continue;
                    $icon = match($key) {
                        'ruhsat' => '📄', 'ehliyet' => '🪪', 'tutanak' => '📋',
                        'hasar_foto' => '📸', 'ekspertiz' => '🔍', 'onarim' => '🔧', 'diger' => '📁',
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
        <?php endif; ?>

        <div class="doc-grid" id="docGrid">
            <?php foreach ($documents as $doc): ?>
            <div class="doc-card" data-id="<?= (int)$doc['id'] ?>">
                <a href="/<?= e($doc['file_path']) ?>" target="_blank" class="doc-thumb">
                    <img src="/<?= e($doc['file_path']) ?>" alt="<?= e($doc['original_name']) ?>" loading="lazy">
                </a>
                <div class="doc-info">
                    <span class="doc-cat"><?= e($categories[$doc['category']] ?? $doc['category']) ?></span>
                    <span class="doc-name"><?= e($doc['original_name']) ?></span>
                    <span class="doc-meta"><?= e($doc['uploader_name']) ?> · <?= date('d.m.Y H:i', strtotime($doc['uploaded_at'])) ?></span>
                </div>
                <?php if ($permissions['can_delete_docs']): ?>
                <button class="doc-delete" data-id="<?= (int)$doc['id'] ?>" title="Sil">✕</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($documents)): ?>
        <p class="empty-state">Henüz evrak yüklenmemiş.</p>
        <?php endif; ?>
    </div>

    <div class="tab-content" id="tab-timeline">
        <div class="timeline">
            <?php foreach ($logs as $log): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <p><?= e($log['action_description']) ?></p>
                    <span class="timeline-meta"><?= e($log['user_name']) ?> · <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tab-content" id="tab-info">
        <div class="info-grid">
            <div class="info-section">
                <h3>Araç</h3>
                <dl>
                    <dt>Plaka</dt><dd><?= e($file['plate']) ?></dd>
                    <dt>Marka/Model</dt><dd><?= e($file['brand'] . ' ' . $file['model']) ?></dd>
                    <dt>Yıl</dt><dd><?= e((string)($file['year'] ?? '-')) ?></dd>
                    <dt>Renk</dt><dd><?= e($file['color'] ?? '-') ?></dd>
                    <dt>Şasi No</dt><dd><?= e($file['chassis_no'] ?? '-') ?></dd>
                </dl>
            </div>
            <div class="info-section">
                <h3>Müşteri</h3>
                <dl>
                    <dt>Ad</dt><dd><?= e($file['customer_name']) ?></dd>
                    <dt>Telefon</dt>
                    <dd>
                        <?= e($file['customer_phone'] ?? '-') ?>
                        <?php if ($waUrl): ?>
                        <a class="btn-wa btn-wa-inline" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"
                           data-file-id="<?= (int)$fileId ?>" data-status="<?= e($file['status']) ?>">WhatsApp</a>
                        <?php endif; ?>
                    </dd>
                    <dt>TC/VKN</dt><dd><?= e($file['tc_vkn']) ?></dd>
                </dl>
            </div>
            <div class="info-section">
                <h3>Sigorta</h3>
                <dl>
                    <dt>Şirket</dt><dd><?= e($file['insurance_company'] ?? '-') ?></dd>
                    <dt>Poliçe No</dt><dd><?= e($file['policy_no'] ?? '-') ?></dd>
                    <dt>Hasar No</dt><dd><?= e($file['claim_no'] ?? '-') ?></dd>
                </dl>
            </div>
            <div class="info-section">
                <h3>Dosya</h3>
                <dl>
                    <dt>Danışman</dt><dd><?= e($file['advisor_name']) ?></dd>
                    <dt>Oluşturulma</dt><dd><?= date('d.m.Y H:i', strtotime($file['created_at'])) ?></dd>
                    <dt>Not</dt><dd><?= e($file['note'] ?? '-') ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var fileId = <?= (int)$fileId ?>;

    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });

    var statusSelect = document.getElementById('statusSelect');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            var formData = new FormData();
            formData.append('csrf', csrf);
            formData.append('damage_file_id', fileId);
            formData.append('status', this.value);
            fetch('/api/status.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        showToast('Durum güncellendi', 'success');
                        if (data.whatsapp) {
                            showWaPrompt(data.whatsapp, data.plate);
                            setTimeout(function() { location.reload(); }, 5000);
                        } else {
                            location.reload();
                        }
                    } else {
                        showToast(data.error || 'Hata', 'error');
                        location.reload();
                    }
                });
        });
    }

    function uploadFiles(category, files) {
        var formData = new FormData();
        formData.append('csrf', csrf);
        formData.append('damage_file_id', fileId);
        formData.append('category', category);
        for (var i = 0; i < files.length; i++) formData.append('files[]', files[i]);

        fetch('/api/upload.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    showToast(data.uploaded.length + ' evrak yüklendi', 'success');
                    location.reload();
                } else {
                    showToast(data.error || 'Yükleme hatası', 'error');
                }
            });
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

    document.getElementById('docGrid').addEventListener('click', function(e) {
        var btn = e.target.closest('.doc-delete');
        if (!btn) return;
        if (!confirm('Bu evrak silinsin mi?')) return;
        var formData = new FormData();
        formData.append('csrf', csrf);
        formData.append('doc_id', btn.dataset.id);
        fetch('/api/delete_doc.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    btn.closest('.doc-card').remove();
                    showToast('Evrak silindi', 'success');
                } else {
                    showToast(data.error || 'Hata', 'error');
                }
            });
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

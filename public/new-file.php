<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
if ($currentUser['role'] === 'workshop' || $currentUser['role'] === 'admin') {
    header('Location: ' . ($currentUser['role'] === 'admin' ? '/admin/' : '/dashboard.php'));
    exit;
}

$pageTitle = 'Yeni Dosya';
$activeNav = 'new-file';
$categories = category_labels();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Yeni Hasar Dosyası</h1>
</div>

<div class="new-file-container">
    <!-- Step 1: Form -->
    <div id="step1" class="step-panel active">
        <div class="step-indicator">
            <span class="step active">1</span>
            <span class="step-line"></span>
            <span class="step">2</span>
        </div>
        <h2>Müşteri & Araç Bilgileri</h2>
        <form id="createFileForm" class="mobile-form">
            <div class="form-group">
                <label for="plate">Plaka *</label>
                <input type="text" id="plate" name="plate" required class="form-input" placeholder="35 ABC 123" autocomplete="off">
                <div id="plateSuggestions" class="suggestions"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="brand">Marka *</label>
                    <input type="text" id="brand" name="brand" required class="form-input">
                </div>
                <div class="form-group">
                    <label for="model">Model *</label>
                    <input type="text" id="model" name="model" required class="form-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="year">Yıl</label>
                    <input type="number" id="year" name="year" class="form-input" min="1990" max="2030">
                </div>
                <div class="form-group">
                    <label for="color">Renk</label>
                    <input type="text" id="color" name="color" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label for="chassis_no">Şasi No</label>
                <input type="text" id="chassis_no" name="chassis_no" class="form-input">
            </div>
            <hr class="form-divider">
            <div class="form-group">
                <label for="customer_name">Müşteri Adı *</label>
                <input type="text" id="customer_name" name="customer_name" required class="form-input">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="customer_phone">Telefon</label>
                    <input type="tel" id="customer_phone" name="customer_phone" class="form-input">
                </div>
                <div class="form-group">
                    <label for="tc_vkn">TC/VKN *</label>
                    <input type="text" id="tc_vkn" name="tc_vkn" required class="form-input">
                </div>
            </div>
            <hr class="form-divider">
            <div class="form-group">
                <label for="insurance_company">Sigorta Şirketi</label>
                <?php $insurers = insurance_companies(true); ?>
                <?php if ($insurers): ?>
                <select id="insurance_company" name="insurance_company" class="form-input">
                    <option value="">Seçiniz</option>
                    <?php foreach ($insurers as $ins): ?>
                    <option value="<?= e($ins['name']) ?>"
                        data-labor="<?= e((string)$ins['labor_discount']) ?>"
                        data-parts="<?= e((string)$ins['parts_discount']) ?>">
                        <?= e($ins['name']) ?> (İşçilik %<?= e((string)$ins['labor_discount']) ?> / Parça %<?= e((string)$ins['parts_discount']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" id="insurance_company" name="insurance_company" class="form-input">
                <?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="policy_no">Poliçe No</label>
                    <input type="text" id="policy_no" name="policy_no" class="form-input">
                </div>
                <div class="form-group">
                    <label for="claim_no">Hasar No</label>
                    <input type="text" id="claim_no" name="claim_no" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label for="note">Not</label>
                <textarea id="note" name="note" class="form-input" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="createBtn">Dosya Aç</button>
        </form>
    </div>

    <!-- Step 2: Upload -->
    <div id="step2" class="step-panel">
        <div class="step-indicator">
            <span class="step done">✓</span>
            <span class="step-line done"></span>
            <span class="step active">2</span>
        </div>
        <div class="file-created-banner">
            <h2 id="createdFileNumber"></h2>
            <p>Evrak yüklemeye başlayabilirsiniz</p>
            <a id="goToFileLink" href="#" class="btn btn-ghost btn-sm">Dosya Detayına Git →</a>
        </div>

        <div class="category-grid" id="categoryGrid">
            <?php foreach ($categories as $key => $label):
                $icon = match($key) {
                    'ruhsat' => '📄', 'ehliyet' => '🪪', 'tutanak' => '📋',
                    'hasar_foto' => '📸', 'ekspertiz' => '🔍', 'onarim' => '🔧', 'diger' => '📁',
                    default => '📎'
                };
            ?>
            <div class="category-card" data-category="<?= e($key) ?>">
                <span class="cat-icon"><?= $icon ?></span>
                <span class="cat-label"><?= e($label) ?></span>
                <input type="file" class="cat-input" accept="image/jpeg,image/png,image/webp" capture="environment"
                       <?= $key === 'hasar_foto' ? 'multiple' : '' ?>>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="dropzone" id="dropzone">
            <p>Fotoğraf sürükleyip bırakın veya tıklayın</p>
            <input type="file" id="dropInput" accept="image/jpeg,image/png,image/webp" multiple>
        </div>

        <div id="uploadPreview" class="upload-preview"></div>

        <a href="/dashboard.php" class="btn btn-primary btn-block btn-lg">Panoya Dön</a>
    </div>
</div>

<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var fileId = null;
    var plateTimer = null;

    document.getElementById('plate').addEventListener('input', function() {
        clearTimeout(plateTimer);
        var q = this.value.trim();
        if (q.length < 2) { document.getElementById('plateSuggestions').innerHTML = ''; return; }
        plateTimer = setTimeout(function() {
            fetch('/api/plate_search.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var html = '';
                    (data.results || []).forEach(function(v) {
                        html += '<div class="suggestion-item" data-plate="' + v.plate + '">' +
                            '<strong>' + v.plate + '</strong> — ' + v.brand + ' ' + v.model +
                            ' (' + v.customer_name + ')</div>';
                    });
                    document.getElementById('plateSuggestions').innerHTML = html;
                });
        }, 300);
    });

    document.getElementById('plateSuggestions').addEventListener('click', function(e) {
        var item = e.target.closest('.suggestion-item');
        if (!item) return;
        var plate = item.dataset.plate;
        fetch('/api/plate_search.php?q=' + encodeURIComponent(plate))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.results && data.results[0]) {
                    var v = data.results[0];
                    document.getElementById('plate').value = v.plate;
                    document.getElementById('brand').value = v.brand;
                    document.getElementById('model').value = v.model;
                    document.getElementById('year').value = v.year || '';
                    document.getElementById('color').value = v.color || '';
                    document.getElementById('chassis_no').value = v.chassis_no || '';
                    document.getElementById('customer_name').value = v.customer_name;
                    document.getElementById('customer_phone').value = v.customer_phone || '';
                    document.getElementById('tc_vkn').value = v.tc_vkn;
                }
                document.getElementById('plateSuggestions').innerHTML = '';
            });
    });

    document.getElementById('createFileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('createBtn');
        btn.disabled = true;
        btn.textContent = 'Oluşturuluyor...';

        var formData = new FormData(this);
        formData.append('csrf', csrf);

        fetch('/api/create_file.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = 'Dosya Aç';
                if (data.ok) {
                    fileId = data.file_id;
                    document.getElementById('createdFileNumber').textContent = data.file_number;
                    document.getElementById('goToFileLink').href = '/file.php?id=' + fileId;
                    document.getElementById('step1').classList.remove('active');
                    document.getElementById('step2').classList.add('active');
                    showToast('Dosya oluşturuldu: ' + data.file_number, 'success');
                } else {
                    showToast(data.error || 'Hata', 'error');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = 'Dosya Aç';
                showToast('Bağlantı hatası', 'error');
            });
    });

    function uploadFiles(category, files) {
        if (!fileId || !files.length) return;
        var formData = new FormData();
        formData.append('csrf', csrf);
        formData.append('damage_file_id', fileId);
        formData.append('category', category);
        for (var i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        var previewId = 'upload-' + Date.now();
        var preview = document.getElementById('uploadPreview');
        var card = document.createElement('div');
        card.className = 'preview-card uploading';
        card.id = previewId;
        card.innerHTML = '<div class="preview-info">Yükleniyor: ' + files.length + ' dosya (' + category + ')</div><div class="progress-bar"><div class="progress-fill"></div></div>';
        preview.prepend(card);

        fetch('/api/upload.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var el = document.getElementById(previewId);
                if (data.ok && data.uploaded.length) {
                    el.className = 'preview-card success';
                    var imgs = data.uploaded.map(function(u) {
                        return '<img src="' + u.file_path + '" alt="' + u.original_name + '">';
                    }).join('');
                    el.innerHTML = '<div class="preview-info">✓ ' + data.uploaded.length + ' dosya yüklendi</div><div class="preview-images">' + imgs + '</div>';
                    showToast(data.uploaded.length + ' evrak yüklendi', 'success');
                } else {
                    el.className = 'preview-card error';
                    el.innerHTML = '<div class="preview-info">✗ ' + (data.error || data.errors.join(', ')) + '</div>';
                    showToast(data.error || 'Yükleme hatası', 'error');
                }
            })
            .catch(function() {
                document.getElementById(previewId).className = 'preview-card error';
                showToast('Bağlantı hatası', 'error');
            });
    }

    document.querySelectorAll('.category-card').forEach(function(card) {
        var input = card.querySelector('.cat-input');
        card.addEventListener('click', function(e) {
            if (e.target !== input) input.click();
        });
        input.addEventListener('change', function() {
            if (this.files.length) uploadFiles(card.dataset.category, this.files);
            this.value = '';
        });
    });

    var dropzone = document.getElementById('dropzone');
    var dropInput = document.getElementById('dropInput');
    dropzone.addEventListener('click', function() { dropInput.click(); });
    dropzone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (e.dataTransfer.files.length) uploadFiles('hasar_foto', e.dataTransfer.files);
    });
    dropInput.addEventListener('change', function() {
        if (this.files.length) uploadFiles('hasar_foto', this.files);
        this.value = '';
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

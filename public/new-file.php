<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
require_perm($currentUser, 'hasar_create_file');

$pageTitle = 'Yeni Dosya';
$activeNav = 'new-file';
$categories = category_labels();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Yeni Hasar Dosyası</h1>
</div>

<div class="new-file-container">
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
                <input type="text" id="plate" name="plate" required class="form-input" placeholder="35ABC35" autocomplete="off" maxlength="9" style="text-transform:uppercase">
                <small class="form-hint">Format: 35ABC35 (boşluksuz, büyük harf)</small>
                <div id="plateSuggestions" class="suggestions"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="brand">Marka</label>
                    <input type="text" id="brand" name="brand" class="form-input">
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <input type="text" id="model" name="model" class="form-input">
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
            <div class="form-group">
                <label for="odometer_km">KM</label>
                <input type="number" id="odometer_km" name="odometer_km" class="form-input" min="0" max="9999999" step="1" placeholder="Örn: 85600">
            </div>
            <hr class="form-divider">
            <div class="form-group">
                <label for="customer_name">Müşteri Adı *</label>
                <input type="text" id="customer_name" name="customer_name" required class="form-input">
            </div>
            <div class="form-group">
                <label for="customer_phone">Telefon *</label>
                <input type="tel" id="customer_phone" name="customer_phone" required class="form-input">
            </div>
            <div class="form-group">
                <label for="customer_address">Adres *</label>
                <input type="text" id="customer_address" name="customer_address" required class="form-input">
            </div>
            <div class="form-group">
                <label for="tc_vkn">TC/VKN</label>
                <input type="text" id="tc_vkn" name="tc_vkn" class="form-input">
            </div>
            <hr class="form-divider">
            <div class="form-group">
                <label for="work_order_no">İş emri no (özel)</label>
                <input type="text" id="work_order_no" name="work_order_no" class="form-input" placeholder="İsteğe bağlı">
            </div>
            <div class="form-group">
                <label for="insurance_company">Sigorta Şirketi</label>
                <?php $insurers = insurance_companies(true); ?>
                <?php if ($insurers): ?>
                <select id="insurance_company" name="insurance_company" class="form-input">
                    <option value="">Seçiniz</option>
                    <?php foreach ($insurers as $ins): ?>
                    <option value="<?= e($ins['name']) ?>"><?= e($ins['name']) ?></option>
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
            <hr class="form-divider">
            <h3 class="form-section-title">Hasar bilgisi</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="damage_date">Hasar tarihi</label>
                    <input type="date" id="damage_date" name="damage_date" class="form-input">
                </div>
                <div class="form-group">
                    <label for="damage_time">Hasar saati</label>
                    <input type="time" id="damage_time" name="damage_time" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label for="damage_type">Hasar şekli</label>
                <input type="text" id="damage_type" name="damage_type" class="form-input" list="damageTypeList" placeholder="Örn: Çarpışma">
                <datalist id="damageTypeList">
                    <?php foreach (damage_type_options() as $opt): ?>
                    <option value="<?= e($opt) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="damage_place">Hasar yeri</label>
                <input type="text" id="damage_place" name="damage_place" class="form-input" placeholder="İl / ilçe / cadde">
            </div>
            <div class="form-group">
                <label>Araç şu an nerede?</label>
                <div class="loc-toggle">
                    <label class="loc-option">
                        <input type="radio" name="vehicle_location" value="serviste" checked>
                        <span>Serviste</span>
                    </label>
                    <label class="loc-option">
                        <input type="radio" name="vehicle_location" value="musteride">
                        <span>Müşteride</span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label for="note">Not</label>
                <textarea id="note" name="note" class="form-input" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="createBtn">Dosya Aç</button>
        </form>
    </div>

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
                <input type="file" class="cat-input" multiple
                       accept="<?= e(upload_accept_documents()) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="upload-quick-actions" data-category="hasar_foto">
            <label class="btn btn-secondary btn-sm upload-picker-btn">
                📷 Kamera ile çek
                <input type="file" class="upload-picker-input" accept="image/*" capture="environment" data-source="camera">
            </label>
            <label class="btn btn-secondary btn-sm upload-picker-btn">
                🖼️ Galeri / dosya seç
                <input type="file" class="upload-picker-input" accept="<?= e(upload_accept_documents()) ?>" multiple data-source="gallery">
            </label>
        </div>
        <div class="dropzone" id="dropzone">
            <p>Fotoğraf veya PDF/Word/Excel sürükleyip bırakın (çoklu seçim)</p>
            <input type="file" class="dropzone-input" id="dropInput" accept="<?= e(upload_accept_documents()) ?>" multiple>
        </div>
        <div id="uploadPreview" class="upload-preview"></div>
        <a href="/dashboard.php" class="btn btn-primary btn-block btn-lg">Panoya Dön</a>
    </div>
</div>

<?php
$pageScript = <<<'JS'
(function() {
    var fileId = null;
    var plateTimer = null;
    var plateInput = document.getElementById('plate');

    function normalizePlate(val) {
        return (val || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function isValidPlate(p) {
        return /^\d{2}[A-Z]{1,3}\d{2,4}$/.test(p);
    }

    function apiGet(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function(r) { return r.json(); });
    }

    plateInput.addEventListener('input', function() {
        var raw = this.value;
        var norm = normalizePlate(raw);
        if (norm !== raw.replace(/\s/g, '')) {
            this.value = norm;
        }
        clearTimeout(plateTimer);
        if (norm.length < 2) { document.getElementById('plateSuggestions').innerHTML = ''; return; }
        plateTimer = setTimeout(function() {
            apiGet('/api/plate_search.php?q=' + encodeURIComponent(norm))
                .then(function(data) {
                    var html = '';
                    (data.results || []).forEach(function(v) {
                        html += '<div class="suggestion-item" data-plate="' + v.plate + '">' +
                            '<strong>' + v.plate + '</strong> — ' + v.brand + ' ' + v.model +
                            ' (' + v.customer_name + ')</div>';
                    });
                    document.getElementById('plateSuggestions').innerHTML = html;
                })
                .catch(function() {});
        }, 300);
    });

    document.getElementById('plateSuggestions').addEventListener('click', function(e) {
        var item = e.target.closest('.suggestion-item');
        if (!item) return;
        apiGet('/api/plate_search.php?q=' + encodeURIComponent(item.dataset.plate))
            .then(function(data) {
                if (data.results && data.results[0]) {
                    var v = data.results[0];
                    plateInput.value = normalizePlate(v.plate);
                    document.getElementById('brand').value = v.brand || '';
                    document.getElementById('model').value = v.model || '';
                    document.getElementById('year').value = v.year || '';
                    document.getElementById('color').value = v.color || '';
                    document.getElementById('chassis_no').value = v.chassis_no || '';
                    document.getElementById('odometer_km').value = v.odometer_km || '';
                    document.getElementById('customer_name').value = v.customer_name || '';
                    document.getElementById('customer_phone').value = v.customer_phone || '';
                    document.getElementById('customer_address').value = v.customer_address || '';
                    document.getElementById('tc_vkn').value = v.tc_vkn || '';
                }
                document.getElementById('plateSuggestions').innerHTML = '';
            });
    });

    document.getElementById('createFileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var plate = normalizePlate(plateInput.value);
        plateInput.value = plate;
        if (!isValidPlate(plate)) {
            showToast('Geçerli plaka giriniz (ör. 35ABC35)', 'error');
            return;
        }
        var btn = document.getElementById('createBtn');
        btn.disabled = true;
        btn.textContent = 'Oluşturuluyor...';
        var formData = new FormData(this);
        formData.set('plate', plate);
        apiFetch('/api/create_file.php', { method: 'POST', body: formData })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = 'Dosya Aç';
                fileId = data.file_id;
                document.getElementById('createdFileNumber').textContent = data.file_number;
                document.getElementById('goToFileLink').href = '/file.php?id=' + fileId;
                document.getElementById('step1').classList.remove('active');
                document.getElementById('step2').classList.add('active');
                showToast('Dosya oluşturuldu: ' + data.file_number, 'success');
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Dosya Aç';
                var msg = (err && err.error) ? err.error : 'Bağlantı hatası';
                if (msg.indexOf('Oturum') !== -1 || msg.indexOf('CSRF') !== -1) {
                    msg += ' — sayfayı yenileyip tekrar giriş yapın';
                }
                showToast(msg, 'error');
            });
    });

    function uploadHasarPhotos(files) {
        var picked = snapshotInputFiles(files);
        if (!picked.length) {
            showToast('Dosya seçilmedi — lütfen tekrar deneyin', 'error');
            return;
        }
        uploadDocuments({
            getFileId: function() { return fileId; },
            category: 'hasar_foto',
            files: picked,
            previewEl: document.getElementById('uploadPreview'),
            noFileMessage: 'Önce dosyayı oluşturun'
        }).then(function(data) {
            showToast(((data.uploaded && data.uploaded.length) || 0) + ' evrak yüklendi', 'success');
        }).catch(function(err) {
            showToast((err && err.error) || 'Yükleme hatası', 'error');
        });
    }

    bindCategoryUpload({
        getFileId: function() { return fileId; },
        gridSelector: '#categoryGrid',
        previewEl: document.getElementById('uploadPreview'),
        noFileMessage: 'Önce dosyayı oluşturun'
    });
    bindUploadPickers({
        getFileId: function() { return fileId; },
        previewEl: document.getElementById('uploadPreview'),
        noFileMessage: 'Önce dosyayı oluşturun'
    });

    var dropzone = document.getElementById('dropzone');
    var dropInput = document.getElementById('dropInput');
    dropzone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (e.dataTransfer.files.length) uploadHasarPhotos(e.dataTransfer.files);
    });
    dropInput.addEventListener('change', function() {
        var inputEl = this;
        readInputFiles(inputEl).then(function(files) {
            inputEl.value = '';
            if (files.length) uploadHasarPhotos(files);
        });
    });
})();
JS;

require __DIR__ . '/../includes/footer.php';

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
            c.name AS customer_name, c.phone AS customer_phone, c.tc_vkn, c.email AS customer_email, c.address AS customer_address,
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
    'SELECT fd.*, COALESCE(u.name, \'Müşteri\') AS uploader_name FROM file_documents fd
     LEFT JOIN users u ON u.id = fd.uploaded_by WHERE fd.damage_file_id = ? ORDER BY fd.uploaded_at DESC'
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
$insCompanies = insurance_companies(true);

require __DIR__ . '/../includes/header.php';
?>

<div class="file-detail">
    <div class="file-header">
        <div class="file-header-left">
            <?= plate_badge_html($file['plate'], $file['work_order_no'] ?? null) ?>
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
                wa_status_message($file['customer_name'], $file['plate'], $file['file_number'], $file['status'], $file['work_order_no'] ?? null)
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
        <?php if (!empty($permissions['can_grant_customer_upload'])): ?>
        <div class="grant-panel" id="customerGrantPanel" data-active="<?= !empty($permissions['customer_upload_active']) ? '1' : '0' ?>">
            <div class="grant-panel-head">
                <strong>Müşteri evrak yükleme</strong>
                <span id="custGrantStatus" class="<?= !empty($permissions['customer_upload_active']) ? 'grant-active' : 'grant-idle' ?>">
                    <?= !empty($permissions['customer_upload_active'])
                        ? 'Açık · ' . e($permissions['customer_upload_remaining'] ?? '')
                        : 'Kapalı' ?>
                </span>
            </div>
            <p class="grant-hint">Açtığınızda müşteri plakasıyla giriş yapıp eksik evrak fotoğrafı yükleyebilir. Süre bitince otomatik kapanır.</p>
            <label class="grant-toggle-row">
                <span>İzin durumu</span>
                <input type="checkbox" id="custGrantToggle" <?= !empty($permissions['customer_upload_active']) ? 'checked' : '' ?>>
                <span class="grant-toggle-ui"></span>
            </label>
            <div class="form-group" style="margin-top:.75rem">
                <label for="customerGrantNote">Eksik evrak notu (müşteriye görünür)</label>
                <input type="text" id="customerGrantNote" class="form-input" maxlength="255"
                       placeholder="Örn: ruhsat ve ehliyet fotoğrafı"
                       value="<?= e($permissions['customer_upload_note'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="customerGrantHours">İzin süresi</label>
                <select id="customerGrantHours" class="form-input">
                    <?php
                    $hourOpts = [12 => '12 saat', 24 => '24 saat', 48 => '48 saat', 72 => '72 saat', 168 => '7 gün'];
                    $selH = (int) ($permissions['customer_upload_hours'] ?? 48);
                    if (!isset($hourOpts[$selH])) {
                        $selH = 48;
                    }
                    foreach ($hourOpts as $h => $lab):
                    ?>
                    <option value="<?= (int)$h ?>" <?= $selH === (int)$h ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grant-actions">
                <button type="button" class="btn btn-primary" id="custGrantSave">Kaydet / Uygula</button>
                <?php if (!empty($permissions['customer_upload_active'])):
                    $portalUrl = customer_portal_url($file['plate'], $permissions['customer_upload_token'] ?? null);
                    $waInvite = wa_url(
                        $file['customer_phone'] ?? null,
                        wa_customer_docs_message(
                            (string) $file['customer_name'],
                            (string) $file['plate'],
                            (string) $file['file_number'],
                            $portalUrl,
                            (int) ($permissions['customer_upload_hours'] ?? 48),
                            $permissions['customer_upload_note'] ?? null,
                            $file['work_order_no'] ?? null
                        )
                    );
                    if ($waInvite):
                ?>
                <a class="btn btn-wa" id="custWaBtn" href="<?= e($waInvite) ?>" target="_blank" rel="noopener"
                   data-file-id="<?= (int)$fileId ?>" data-status="<?= e($file['status']) ?>">WhatsApp Gönder</a>
                <?php endif; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($permissions['can_upload']): ?>
        <div class="upload-section">
            <h3>Evrak Yükle</h3>
            <div class="category-grid compact" id="uploadCategoryGrid">
                <?php foreach ($categories as $key => $label):
                    if (!in_array($key, $permissions['allowed_categories'], true)) continue;
                    $icon = match($key) {
                        'ruhsat' => '📄', 'ehliyet' => '🪪', 'tutanak' => '📋',
                        'hasar_foto' => '📸', 'ekspertiz' => '🔍', 'taahhut' => '📝', 'teslim' => '📦', 'ibra' => '✍️',
                        'onarim' => '🔧', 'diger' => '📁',
                        default => '📎'
                    };
                ?>
                <div class="category-card small" data-category="<?= e($key) ?>">
                    <span class="cat-icon"><?= $icon ?></span>
                    <span class="cat-label"><?= e($label) ?></span>
                    <input type="file" class="cat-input" multiple
                           accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.heic,.heif">
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (in_array('hasar_foto', $permissions['allowed_categories'], true)): ?>
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
            <?php endif; ?>
            <div id="uploadPreview" class="upload-preview"></div>
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
        <?php if (!empty($permissions['can_edit'])): ?>
        <form id="fileInfoForm" class="info-edit-form">
            <div class="info-grid">
                <div class="info-section">
                    <h3>Araç</h3>
                    <div class="form-group"><label>Plaka *</label><input class="form-input" name="plate" required value="<?= e($file['plate']) ?>"></div>
                    <div class="form-row-2">
                        <div class="form-group"><label>Marka *</label><input class="form-input" name="brand" required value="<?= e($file['brand']) ?>"></div>
                        <div class="form-group"><label>Model *</label><input class="form-input" name="model" required value="<?= e($file['model']) ?>"></div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group"><label>Yıl</label><input class="form-input" type="number" name="year" min="1980" max="2100" value="<?= e((string)($file['year'] ?? '')) ?>"></div>
                        <div class="form-group"><label>Renk</label><input class="form-input" name="color" value="<?= e($file['color'] ?? '') ?>"></div>
                    </div>
                    <div class="form-group"><label>Şasi No</label><input class="form-input" name="chassis_no" value="<?= e($file['chassis_no'] ?? '') ?>"></div>
                </div>
                <div class="info-section">
                    <h3>Müşteri</h3>
                    <div class="form-group"><label>Ad Soyad *</label><input class="form-input" name="customer_name" required value="<?= e($file['customer_name']) ?>"></div>
                    <div class="form-group"><label>Telefon *</label><input class="form-input" type="tel" name="customer_phone" required value="<?= e($file['customer_phone'] ?? '') ?>"></div>
                    <div class="form-group"><label>Adres *</label><input class="form-input" name="customer_address" required value="<?= e($file['customer_address'] ?? '') ?>"></div>
                    <div class="form-group"><label>TC / VKN</label><input class="form-input" name="tc_vkn" value="<?= e($file['tc_vkn']) ?>"></div>
                    <div class="form-group"><label>E-posta</label><input class="form-input" type="email" name="customer_email" value="<?= e($file['customer_email'] ?? '') ?>"></div>
                </div>
                <div class="info-section">
                    <h3>Sigorta</h3>
                    <div class="form-group">
                        <label>Şirket</label>
                        <?php if ($insCompanies): ?>
                        <select class="form-input" name="insurance_company">
                            <option value="">— Seçin —</option>
                            <?php foreach ($insCompanies as $ic):
                                $iname = $ic['name'];
                                $sel = ($file['insurance_company'] ?? '') === $iname ? 'selected' : '';
                            ?>
                            <option value="<?= e($iname) ?>" <?= $sel ?>><?= e($iname) ?></option>
                            <?php endforeach; ?>
                            <?php if (($file['insurance_company'] ?? '') !== '' && !in_array($file['insurance_company'], array_column($insCompanies, 'name'), true)): ?>
                            <option value="<?= e($file['insurance_company']) ?>" selected><?= e($file['insurance_company']) ?> (kayıtlı)</option>
                            <?php endif; ?>
                        </select>
                        <?php else: ?>
                        <input class="form-input" name="insurance_company" value="<?= e($file['insurance_company'] ?? '') ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group"><label>Poliçe No</label><input class="form-input" name="policy_no" value="<?= e($file['policy_no'] ?? '') ?>"></div>
                    <div class="form-group"><label>Hasar No</label><input class="form-input" name="claim_no" value="<?= e($file['claim_no'] ?? '') ?>"></div>
                </div>
                <div class="info-section">
                    <h3>Dosya</h3>
                    <dl class="info-readonly">
                        <dt>Danışman</dt><dd><?= e($file['advisor_name']) ?></dd>
                        <dt>Dosya No</dt><dd><?= e($file['file_number']) ?></dd>
                        <dt>Oluşturulma</dt><dd><?= date('d.m.Y H:i', strtotime($file['created_at'])) ?></dd>
                        <dt>Müşteri evrak izni</dt>
                        <dd><?= !empty($permissions['customer_upload_active']) ? 'Açık' : 'Kapalı' ?></dd>
                    </dl>
                    <div class="form-group"><label>İş emri no (özel)</label><input class="form-input" name="work_order_no" value="<?= e($file['work_order_no'] ?? '') ?>" placeholder="İsteğe bağlı"></div>
                    <div class="form-group"><label>Not</label><textarea class="form-input" name="note" rows="3"><?= e($file['note'] ?? '') ?></textarea></div>
                </div>
            </div>
            <div class="info-edit-actions">
                <button type="submit" class="btn btn-primary">Bilgileri Kaydet</button>
            </div>
        </form>
        <?php else: ?>
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
                    <dt>Adres</dt><dd><?= e($file['customer_address'] ?? '-') ?></dd>
                    <dt>E-posta</dt><dd><?= e($file['customer_email'] ?? '-') ?></dd>
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
                    <dt>İş emri no</dt><dd><?= e($file['work_order_no'] ?? '-') ?></dd>
                    <dt>Oluşturulma</dt><dd><?= date('d.m.Y H:i', strtotime($file['created_at'])) ?></dd>
                    <dt>Müşteri evrak izni</dt>
                    <dd>
                        <?php if (!empty($permissions['customer_upload_active'])): ?>
                            Açık · <?= e($permissions['customer_upload_remaining'] ?? '') ?>
                        <?php else: ?>
                            Kapalı
                        <?php endif; ?>
                    </dd>
                    <dt>Not</dt><dd><?= e($file['note'] ?? '-') ?></dd>
                </dl>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php ob_start(); ?>
(function() {
    var fileId = <?= (int)$fileId ?>;

    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });

    bindCategoryUpload({
        fileId: fileId,
        gridSelector: '#uploadCategoryGrid',
        previewEl: document.getElementById('uploadPreview'),
        reloadOnSuccess: true
    });
    if (document.querySelector('.upload-quick-actions')) {
        bindUploadPickers({
            fileId: fileId,
            previewEl: document.getElementById('uploadPreview'),
            reloadOnSuccess: true
        });
    }

    var statusSelect = document.getElementById('statusSelect');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            var formData = new FormData();
            formData.append('damage_file_id', fileId);
            formData.append('status', this.value);
            apiFetch('/api/status.php', { method: 'POST', body: formData })
                .then(function(data) {
                    showToast('Durum güncellendi', 'success');
                    if (data.whatsapp) {
                        showWaPrompt(data.whatsapp, data.plate);
                        setTimeout(function() { location.reload(); }, 5000);
                    } else {
                        location.reload();
                    }
                })
                .catch(function(err) {
                    showToast((err && err.error) || 'Hata', 'error');
                    location.reload();
                });
        });
    }

    function grantCustomerUpload(hours, revoke) {
        var formData = new FormData();
        formData.append('damage_file_id', fileId);
        if (revoke) {
            formData.append('revoke', '1');
        } else {
            formData.append('hours', String(hours));
            var noteEl = document.getElementById('customerGrantNote');
            if (noteEl) formData.append('note', noteEl.value || '');
        }
        return apiFetch('/api/customer_upload_grant.php', { method: 'POST', body: formData });
    }
    function selectedCustomerGrantHours() {
        var sel = document.getElementById('customerGrantHours');
        return sel ? parseInt(sel.value, 10) : 48;
    }
    var custSave = document.getElementById('custGrantSave');
    var custToggle = document.getElementById('custGrantToggle');
    if (custSave && custToggle) {
        custSave.addEventListener('click', function() {
            var open = custToggle.checked;
            grantCustomerUpload(selectedCustomerGrantHours(), !open)
                .then(function(data) {
                    showToast(open ? 'Müşteri evrak izni açıldı' : 'Müşteri evrak izni kapatıldı', 'success');
                    if (open && data.whatsapp) {
                        showWaPrompt(data.whatsapp, data.plate);
                    }
                    location.reload();
                })
                .catch(function(err) {
                    showToast((err && err.error) || 'Hata', 'error');
                });
        });
        custToggle.addEventListener('change', function() {
            var status = document.getElementById('custGrantStatus');
            if (status) {
                status.textContent = this.checked ? 'Açılacak (Kaydet\'e basın)' : 'Kapatılacak (Kaydet\'e basın)';
                status.className = this.checked ? 'grant-active' : 'grant-idle';
            }
        });
    }

    var infoForm = document.getElementById('fileInfoForm');
    if (infoForm) {
        infoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(infoForm);
            formData.append('damage_file_id', fileId);
            apiFetch('/api/update_file_info.php', { method: 'POST', body: formData })
                .then(function() {
                    showToast('Bilgiler kaydedildi', 'success');
                    location.reload();
                })
                .catch(function(err) {
                    showToast((err && err.error) || 'Kayıt hatası', 'error');
                });
        });
    }

    document.getElementById('docGrid').addEventListener('click', function(e) {
        var btn = e.target.closest('.doc-delete');
        if (!btn) return;
        if (!confirm('Bu evrak silinsin mi?')) return;
        var formData = new FormData();
        formData.append('doc_id', btn.dataset.id);
        apiFetch('/api/delete_doc.php', { method: 'POST', body: formData })
            .then(function() {
                btn.closest('.doc-card').remove();
                showToast('Evrak silindi', 'success');
            })
            .catch(function(err) {
                showToast((err && err.error) || 'Hata', 'error');
            });
    });
})();
<?php
$pageScript = ob_get_clean();
require __DIR__ . '/../includes/footer.php';

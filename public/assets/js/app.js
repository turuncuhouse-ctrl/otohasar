function getCsrfToken() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : '';
}

function apiUpload(url, formData, onProgress) {
    if (!formData.has('csrf')) {
        var token = getCsrfToken();
        if (!token) {
            return Promise.reject({ error: 'Oturum süresi doldu — sayfayı yenileyip tekrar giriş yapın' });
        }
        formData.append('csrf', token);
    }

    return new Promise(function(resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.withCredentials = true;
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable && onProgress) {
                onProgress(Math.round((e.loaded / e.total) * 100));
            }
        };
        xhr.onload = function() {
            var data = {};
            try {
                data = xhr.responseText ? JSON.parse(xhr.responseText) : {};
            } catch (err) {
                reject({ error: 'Sunucu yanıtı geçersiz (' + xhr.status + ')' });
                return;
            }
            if (xhr.status >= 200 && xhr.status < 300 && data.ok !== false) {
                resolve(data);
            } else {
                reject({ error: (data && data.error) ? data.error : ('Yükleme başarısız (' + xhr.status + ')') });
            }
        };
        xhr.onerror = function() {
            reject({ error: 'Bağlantı hatası' });
        };
        xhr.send(formData);
    });
}

function isImageFile(file) {
    if (!file) return false;
    var type = (file.type || '').toLowerCase();
    if (type === '' || type === 'application/octet-stream') return true;
    if (type.indexOf('image/') === 0) return true;
    var name = (file.name || '').toLowerCase();
    return /\.(jpe?g|png|webp|heic|heif)$/.test(name);
}

function filterImageFiles(files) {
    var list = Array.from(files || []).filter(isImageFile);
    var heic = list.filter(function(file) {
        var type = (file.type || '').toLowerCase();
        var name = (file.name || '').toLowerCase();
        return type.indexOf('heic') !== -1 || type.indexOf('heif') !== -1 || /\.heic$/.test(name) || /\.heif$/.test(name);
    });
    if (heic.length && heic.length === list.length) {
        return { files: list, heicOnly: true };
    }
    return { files: list, heicOnly: false };
}

function uploadLabel(file) {
    if (file.name) return file.name;
    if (file.type) return file.type.replace('image/', '').toUpperCase() + ' fotoğraf';
    return 'Fotoğraf';
}

function setPreviewProgress(previewEl, percent, statusText) {
    if (!previewEl) return;
    previewEl.querySelectorAll('.preview-card.uploading').forEach(function(card) {
        var fill = card.querySelector('.progress-fill');
        if (fill) {
            fill.style.width = percent + '%';
            fill.style.animation = 'none';
        }
        var status = card.querySelector('.preview-status');
        if (status && statusText) status.textContent = statusText;
    });
}

function markPreviewDone(previewEl, success, message) {
    if (!previewEl) return;
    previewEl.querySelectorAll('.preview-card.uploading').forEach(function(card) {
        card.classList.remove('uploading');
        card.classList.add(success ? 'success' : 'error');
        var status = card.querySelector('.preview-status');
        if (status) status.textContent = message;
    });
}

function uploadDocuments(opts) {
    opts = opts || {};
    var fileId = typeof opts.getFileId === 'function' ? opts.getFileId() : opts.fileId;
    var files = Array.from(opts.files || []);
    var previewEl = opts.previewEl || null;
    var uploadUrl = opts.uploadUrl || '/api/upload.php';

    if (!fileId) {
        return Promise.reject({ error: opts.noFileMessage || 'Önce dosyayı oluşturun' });
    }
    if (!files.length) {
        return Promise.reject({ error: 'Dosya seçilmedi' });
    }

    var filtered = filterImageFiles(files);
    files = filtered.files;
    if (!files.length) {
        return Promise.reject({ error: 'Geçerli görsel seçilmedi (JPEG, PNG, WebP)' });
    }
    if (filtered.heicOnly) {
        return Promise.reject({
            error: 'HEIC formatı desteklenmiyor. iPhone: Ayarlar > Kamera > Biçimler > En Uyumlu (JPEG) seçin veya JPEG fotoğraf yükleyin.'
        });
    }

    if (previewEl) {
        previewEl.style.display = '';
        files.forEach(function(file) {
            var card = document.createElement('div');
            card.className = 'preview-card uploading';
            var title = document.createElement('strong');
            title.textContent = uploadLabel(file);
            var status = document.createElement('span');
            status.className = 'preview-status';
            status.textContent = 'Yükleniyor…';
            var bar = document.createElement('div');
            bar.className = 'progress-bar';
            var fill = document.createElement('div');
            fill.className = 'progress-fill';
            fill.style.width = '8%';
            bar.appendChild(fill);
            var images = document.createElement('div');
            images.className = 'preview-images';
            card.appendChild(title);
            card.appendChild(status);
            card.appendChild(bar);
            card.appendChild(images);
            previewEl.prepend(card);

            if (file.type && file.type.indexOf('image/') === 0) {
                var img = document.createElement('img');
                images.appendChild(img);
                var reader = new FileReader();
                reader.onload = function(e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });
    }

    var formData = new FormData();
    formData.append('damage_file_id', fileId);
    formData.append('category', opts.category);
    files.forEach(function(file) {
        formData.append('files[]', file);
    });

    return apiUpload(uploadUrl, formData, function(percent) {
        setPreviewProgress(previewEl, percent, percent >= 100 ? 'Kaydediliyor…' : ('Yükleniyor… %' + percent));
    }).then(function(data) {
        var count = (data.uploaded && data.uploaded.length) || 0;
        markPreviewDone(previewEl, true, count + ' evrak yüklendi');
        if (data.errors && data.errors.length) {
            showToast(data.errors.join(' · '), 'error');
        }
        return data;
    }).catch(function(err) {
        markPreviewDone(previewEl, false, (err && err.error) || 'Yükleme hatası');
        throw err;
    });
}

function bindCategoryUpload(opts) {
    document.querySelectorAll(opts.gridSelector + ' .category-card').forEach(function(card) {
        var input = card.querySelector('.cat-input');
        if (!input) return;
        input.addEventListener('change', function() {
            if (!this.files.length) return;
            var files = this.files;
            var category = card.dataset.category;
            this.value = '';
            uploadDocuments({
                getFileId: opts.getFileId,
                fileId: opts.fileId,
                category: category,
                files: files,
                previewEl: opts.previewEl,
                uploadUrl: opts.uploadUrl,
                noFileMessage: opts.noFileMessage
            }).then(function(data) {
                showToast(((data.uploaded && data.uploaded.length) || 0) + ' evrak yüklendi', 'success');
                if (opts.reloadOnSuccess) {
                    setTimeout(function() { location.reload(); }, 900);
                }
            }).catch(function(err) {
                showToast((err && err.error) || 'Yükleme hatası', 'error');
            });
        });
    });
}

function bindUploadPickers(opts) {
    document.querySelectorAll(opts.wrapSelector || '.upload-quick-actions').forEach(function(wrap) {
        var category = wrap.dataset.category || opts.category || 'hasar_foto';
        wrap.querySelectorAll('.upload-picker-input').forEach(function(input) {
            input.addEventListener('change', function() {
                if (!this.files.length) return;
                var files = this.files;
                this.value = '';
                uploadDocuments({
                    getFileId: opts.getFileId,
                    fileId: opts.fileId,
                    category: category,
                    files: files,
                    previewEl: opts.previewEl,
                    uploadUrl: opts.uploadUrl,
                    noFileMessage: opts.noFileMessage
                }).then(function(data) {
                    showToast(((data.uploaded && data.uploaded.length) || 0) + ' evrak yüklendi', 'success');
                    if (opts.reloadOnSuccess) {
                        setTimeout(function() { location.reload(); }, 900);
                    }
                }).catch(function(err) {
                    showToast((err && err.error) || 'Yükleme hatası', 'error');
                });
            });
        });
    });
}

function bindQuickPhotoPickers(opts) {
    bindUploadPickers(opts);
}

function apiFetch(url, options) {
    options = options || {};
    options.credentials = 'same-origin';
    if (options.method === 'POST' && options.body instanceof FormData) {
        if (!options.body.has('csrf')) {
            var token = getCsrfToken();
            if (!token) {
                return Promise.reject({ error: 'Oturum süresi doldu — sayfayı yenileyip tekrar giriş yapın' });
            }
            options.body.append('csrf', token);
        }
    }
    return fetch(url, options).then(function(r) {
        return r.text().then(function(text) {
            var data = {};
            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    return Promise.reject({ error: 'Sunucu yanıtı geçersiz (' + r.status + ')' });
                }
            }
            if (!r.ok || data.ok === false) {
                return Promise.reject({
                    error: (data && data.error) ? data.error : ('İstek başarısız (' + r.status + ')')
                });
            }
            return data;
        });
    });
}

function showToast(message, type) {
    type = type || 'info';
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity .3s';
        setTimeout(function() { toast.remove(); }, 300);
    }, 3500);
}

function showWaPrompt(url, plate) {
    var container = document.getElementById('toastContainer');
    if (!container || !url) return;
    var toast = document.createElement('div');
    toast.className = 'toast toast-wa';
    var label = document.createElement('span');
    label.textContent = (plate ? plate + ' — ' : '') + 'Müşteriye bildir';
    var link = document.createElement('a');
    link.className = 'btn-wa';
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = 'WhatsApp';
    toast.appendChild(label);
    toast.appendChild(link);
    container.appendChild(toast);
    setTimeout(function() {
        if (toast.parentNode) toast.remove();
    }, 20000);
}

function logWhatsApp(fileId, status) {
    var csrfEl = document.querySelector('meta[name="csrf-token"]');
    if (!csrfEl || !fileId) return;
    var formData = new FormData();
    formData.append('csrf', csrfEl.content);
    formData.append('damage_file_id', fileId);
    formData.append('status', status || '');
    fetch('/api/whatsapp_log.php', { method: 'POST', body: formData, credentials: 'same-origin' }).catch(function() {});
}

document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('navToggle');
    var nav = document.getElementById('mainNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('open');
        });
    }

    document.addEventListener('click', function(e) {
        var wa = e.target.closest('.btn-wa');
        if (!wa) return;
        logWhatsApp(wa.dataset.fileId, wa.dataset.status);
    });
});

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

    if (typeof XMLHttpRequest !== 'undefined' && onProgress) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.withCredentials = true;
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    onProgress(Math.round((e.loaded / e.total) * 100));
                }
            };
            xhr.onload = function() {
                try {
                    resolve(parseUploadResponse(xhr.status, xhr.responseText));
                } catch (err) {
                    reject(err);
                }
            };
            xhr.onerror = function() { reject({ error: 'Bağlantı hatası' }); };
            xhr.send(formData);
        });
    }

    return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(r) {
            return r.text().then(function(text) {
                return parseUploadResponse(r.status, text);
            });
        });
}

function parseUploadResponse(status, text) {
    var data = {};
    if (text) {
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw { error: 'Sunucu yanıtı geçersiz (' + status + ')' };
        }
    }
    if (status >= 200 && status < 300 && data.ok !== false) {
        return data;
    }
    throw { error: (data && data.error) ? data.error : ('Yükleme başarısız (' + status + ')') };
}

function snapshotInputFiles(fileList) {
    var files = [];
    if (!fileList || !fileList.length) return files;
    for (var i = 0; i < fileList.length; i++) {
        var file = fileList[i];
        if (file && file.size > 0) {
            files.push(file);
        }
    }
    return files;
}

function readInputFiles(input, retries) {
    retries = retries || 5;
    return new Promise(function(resolve) {
        function attempt(left) {
            var files = snapshotInputFiles(input.files);
            if (files.length || left <= 0) {
                resolve(files);
                return;
            }
            setTimeout(function() { attempt(left - 1); }, 120);
        }
        attempt(retries);
    });
}

function isHeicFile(file) {
    var type = (file.type || '').toLowerCase();
    var name = (file.name || '').toLowerCase();
    return type.indexOf('heic') !== -1 || type.indexOf('heif') !== -1
        || /\.heic$/.test(name) || /\.heif$/.test(name);
}

function isLikelyImageFile(file) {
    if (!file || !(file.size > 0)) return false;
    var type = (file.type || '').toLowerCase();
    if (!type || type === 'application/octet-stream') return true;
    if (type.indexOf('image/') === 0) return true;
    if (type.indexOf('photo') !== -1) return true;
    var name = (file.name || '').toLowerCase();
    return /\.(jpe?g|png|webp|heic|heif|gif|bmp)$/.test(name);
}

var heic2anyPromise = null;
function loadHeic2Any() {
    if (window.heic2any) return Promise.resolve(window.heic2any);
    if (heic2anyPromise) return heic2anyPromise;
    heic2anyPromise = new Promise(function(resolve, reject) {
        var script = document.createElement('script');
        script.src = '/assets/js/heic2any.min.js?v=12';
        script.async = true;
        script.onload = function() {
            if (window.heic2any) resolve(window.heic2any);
            else reject(new Error('heic2any unavailable'));
        };
        script.onerror = function() { reject(new Error('heic2any load failed')); };
        document.head.appendChild(script);
    });
    return heic2anyPromise;
}

function convertHeicIfNeeded(file) {
    if (!isHeicFile(file)) return Promise.resolve(file);
    return loadHeic2Any().then(function(heic2any) {
        return heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.92
        }).then(function(result) {
            var blob = Array.isArray(result) ? result[0] : result;
            var base = (file.name || 'photo').replace(/\.(heic|heif)$/i, '');
            return new File([blob], base + '.jpg', {
                type: 'image/jpeg',
                lastModified: file.lastModified || Date.now()
            });
        });
    });
}

function prepareUploadFiles(fileList) {
    var files = snapshotInputFiles(fileList);
    if (!files.length) {
        return Promise.reject({ error: 'Dosya seçilmedi — lütfen tekrar deneyin' });
    }
    return Promise.all(files.map(function(file) {
        return convertHeicIfNeeded(file).catch(function() {
            return file;
        });
    })).then(function(converted) {
        var ready = converted.filter(function(file) { return file && file.size > 0; });
        if (!ready.length) {
            return Promise.reject({ error: 'Seçilen dosyalar okunamadı' });
        }
        return ready;
    });
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
    var previewEl = opts.previewEl || null;
    var uploadUrl = opts.uploadUrl || '/api/upload.php';

    if (!fileId) {
        return Promise.reject({ error: opts.noFileMessage || 'Önce dosyayı oluşturun' });
    }

    return prepareUploadFiles(opts.files).then(function(files) {
        if (!files.length) {
            return Promise.reject({ error: 'Dosya seçilmedi' });
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

                var img = document.createElement('img');
                images.appendChild(img);
                var reader = new FileReader();
                reader.onload = function(e) { img.src = e.target.result; };
                reader.onerror = function() {};
                reader.readAsDataURL(file);
            });
        }

        var formData = new FormData();
        formData.append('damage_file_id', fileId);
        formData.append('category', opts.category);
        files.forEach(function(file, index) {
            var uploadFile = file;
            var uploadName = file.name || ('photo_' + (index + 1) + '.jpg');
            if (!/\.(jpe?g|png|webp)$/i.test(uploadName)) {
                uploadName = 'photo_' + (index + 1) + '.jpg';
            }
            if (!file.name || file.name !== uploadName) {
                uploadFile = new File([file], uploadName, {
                    type: file.type || 'image/jpeg',
                    lastModified: file.lastModified || Date.now()
                });
            }
            formData.append('files[]', uploadFile);
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
        });
    }).catch(function(err) {
        markPreviewDone(previewEl, false, (err && err.error) || 'Yükleme hatası');
        throw err;
    });
}

function startUploadFromInput(input, opts, category) {
    var inputEl = input;
    readInputFiles(inputEl).then(function(files) {
        inputEl.value = '';
        if (!files.length) {
            showToast('Dosya seçilmedi — lütfen tekrar deneyin', 'error');
            return;
        }
        uploadDocuments(Object.assign({}, opts, { category: category, files: files }))
            .then(function(data) {
                showToast(((data.uploaded && data.uploaded.length) || 0) + ' evrak yüklendi', 'success');
                if (opts.reloadOnSuccess) {
                    setTimeout(function() { location.reload(); }, 900);
                }
            })
            .catch(function(err) {
                showToast((err && err.error) || 'Yükleme hatası', 'error');
            });
    });
}

function bindCategoryUpload(opts) {
    document.querySelectorAll(opts.gridSelector + ' .category-card').forEach(function(card) {
        var input = card.querySelector('.cat-input');
        if (!input) return;
        input.addEventListener('change', function() {
            startUploadFromInput(this, opts, card.dataset.category);
        });
    });
}

function bindUploadPickers(opts) {
    document.querySelectorAll(opts.wrapSelector || '.upload-quick-actions').forEach(function(wrap) {
        var category = wrap.dataset.category || opts.category || 'hasar_foto';
        wrap.querySelectorAll('.upload-picker-input').forEach(function(input) {
            input.addEventListener('change', function() {
                startUploadFromInput(this, opts, category);
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

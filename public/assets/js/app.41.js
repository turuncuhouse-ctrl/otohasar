function getCsrfToken() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : '';
}

function apiUpload(url, formData, onProgress) {
    if (!(typeof formData.has === 'function' && formData.has('csrf'))) {
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
            xhr.timeout = 180000;
            xhr.onload = function() {
                try {
                    resolve(parseUploadResponse(xhr.status, xhr.responseText));
                } catch (err) {
                    reject(err);
                }
            };
            xhr.ontimeout = function() { reject({ error: 'Yükleme zaman aşımı — tekrar deneyin' }); };
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
            if (status === 413) {
                throw { error: 'Dosya çok büyük. Fotoğrafı küçültüp tekrar deneyin.' };
            }
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

function isPdfFile(file) {
    var type = (file.type || '').toLowerCase();
    var name = (file.name || '').toLowerCase();
    return type === 'application/pdf' || /\.pdf$/.test(name);
}

function isHeicFile(file) {
    var type = (file.type || '').toLowerCase();
    var name = (file.name || '').toLowerCase();
    return type.indexOf('heic') !== -1 || type.indexOf('heif') !== -1
        || /\.heic$/.test(name) || /\.heif$/.test(name);
}

function looksLikeHeicBytes(bytes) {
    if (!bytes || bytes.length < 12) return false;
    if (bytes[0] === 0xFF && bytes[1] === 0xD8) return false;
    if (bytes[0] === 0x89 && bytes[1] === 0x50) return false;
    var ascii = '';
    for (var i = 0; i < Math.min(bytes.length, 16); i++) {
        ascii += String.fromCharCode(bytes[i]);
    }
    return /ftyp(heic|heix|hevc|hevx|mif1|msf1|heim|heis|hevm|hevs)/i.test(ascii);
}

function readFilePrefix(file, n) {
    n = n || 24;
    return new Promise(function(resolve) {
        if (!file || typeof file.slice !== 'function') {
            resolve(null);
            return;
        }
        var blob = file.slice(0, n);
        if (typeof blob.arrayBuffer === 'function') {
            blob.arrayBuffer().then(function(buf) {
                resolve(new Uint8Array(buf));
            }).catch(function() { resolve(null); });
            return;
        }
        var reader = new FileReader();
        reader.onload = function() {
            try {
                resolve(new Uint8Array(reader.result));
            } catch (e) {
                resolve(null);
            }
        };
        reader.onerror = function() { resolve(null); };
        reader.readAsArrayBuffer(blob);
    });
}

function isLikelyImageFile(file) {
    if (!file) return false;
    var type = (file.type || '').toLowerCase();
    if (type === 'application/pdf') return false;
    if (type.indexOf('image/') === 0) return true;
    if (type.indexOf('photo') !== -1) return true;
    if (!type || type === 'application/octet-stream') return true;
    var name = (file.name || '').toLowerCase();
    return /\.(jpe?g|png|webp|heic|heif|gif|bmp)$/.test(name);
}

var heic2anyPromise = null;
function loadHeic2Any() {
    if (window.heic2any) return Promise.resolve(window.heic2any);
    if (heic2anyPromise) return heic2anyPromise;
    heic2anyPromise = new Promise(function(resolve, reject) {
        var script = document.createElement('script');
        script.src = '/assets/js/heic2any.min.js?v=33';
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

function blobToUploadFile(blob, name, type, lastModified) {
    try {
        if (typeof File !== 'undefined') {
            return new File([blob], name, {
                type: type || blob.type || 'image/jpeg',
                lastModified: lastModified || Date.now()
            });
        }
    } catch (e) {}
    try {
        blob.name = name;
    } catch (e2) {}
    return blob;
}

function convertHeicIfNeeded(file) {
    return loadHeic2Any().then(function(heic2any) {
        return heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.92
        }).then(function(result) {
            var blob = Array.isArray(result) ? result[0] : result;
            var base = (file.name || 'photo').replace(/\.(heic|heif)$/i, '');
            return blobToUploadFile(blob, base + '.jpg', 'image/jpeg', file.lastModified);
        });
    });
}

function loadHtmlImage(blob) {
    return new Promise(function(resolve, reject) {
        var url = URL.createObjectURL(blob);
        var img = new Image();
        img.onload = function() {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = function() {
            URL.revokeObjectURL(url);
            reject(new Error('Görüntü okunamadı'));
        };
        img.src = url;
    });
}

function sourceSize(src) {
    return {
        w: src.naturalWidth || src.width || 0,
        h: src.naturalHeight || src.height || 0
    };
}

function loadImageSource(blob, maxEdge) {
    if (typeof createImageBitmap !== 'function') {
        return loadHtmlImage(blob);
    }

    function downscaleBitmap(bmp) {
        var size = sourceSize(bmp);
        if (!size.w || !size.h || (size.w <= maxEdge && size.h <= maxEdge)) {
            return bmp;
        }
        var scale = Math.min(maxEdge / size.w, maxEdge / size.h, 1);
        var rw = Math.max(1, Math.round(size.w * scale));
        var rh = Math.max(1, Math.round(size.h * scale));
        return createImageBitmap(bmp, { resizeWidth: rw, resizeHeight: rh, resizeQuality: 'medium' })
            .then(function(small) {
                try { bmp.close(); } catch (e) {}
                return small;
            })
            .catch(function() { return bmp; });
    }

    return createImageBitmap(blob, { imageOrientation: 'from-image', resizeQuality: 'medium' })
        .then(downscaleBitmap)
        .catch(function() {
            return createImageBitmap(blob).then(downscaleBitmap);
        })
        .catch(function() {
            return loadHtmlImage(blob);
        });
}

function canvasToJpegBlob(canvas, quality) {
    return new Promise(function(resolve, reject) {
        if (typeof canvas.toBlob === 'function') {
            canvas.toBlob(function(blob) {
                if (blob && blob.size > 0) resolve(blob);
                else reject(new Error('toBlob empty'));
            }, 'image/jpeg', quality);
            return;
        }
        try {
            var dataUrl = canvas.toDataURL('image/jpeg', quality);
            var bin = atob(dataUrl.split(',')[1] || '');
            var arr = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
            resolve(new Blob([arr], { type: 'image/jpeg' }));
        } catch (e) {
            reject(e);
        }
    });
}

function drawToCanvas(src, maxEdge) {
    var size = sourceSize(src);
    var w = size.w;
    var h = size.h;
    if (!w || !h) throw new Error('Geçersiz görüntü boyutu');

    var scale = Math.min(maxEdge / w, maxEdge / h, 1);
    var tw = Math.max(1, Math.round(w * scale));
    var th = Math.max(1, Math.round(h * scale));

    var srcW = w;
    var srcH = h;
    var current = src;
    var maxPixels = 16 * 1024 * 1024;
    var maxDim = 4096;

    while ((srcW * srcH > maxPixels || srcW > maxDim || srcH > maxDim) && (srcW > tw * 1.4 || srcH > th * 1.4)) {
        var nw = Math.max(tw, Math.round(srcW * 0.5));
        var nh = Math.max(th, Math.round(srcH * 0.5));
        if (nw * nh > maxPixels) {
            var shrink = Math.sqrt(maxPixels / (srcW * srcH));
            nw = Math.max(tw, Math.round(srcW * shrink));
            nh = Math.max(th, Math.round(srcH * shrink));
        }
        var step = document.createElement('canvas');
        step.width = nw;
        step.height = nh;
        var sctx = step.getContext('2d', { alpha: false });
        if (!sctx) throw new Error('Canvas desteklenmiyor');
        sctx.drawImage(current, 0, 0, nw, nh);
        current = step;
        srcW = nw;
        srcH = nh;
    }

    var canvas = document.createElement('canvas');
    canvas.width = tw;
    canvas.height = th;
    var ctx = canvas.getContext('2d', { alpha: false });
    if (!ctx) throw new Error('Canvas desteklenmiyor');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, tw, th);
    ctx.drawImage(current, 0, 0, tw, th);
    return canvas;
}

function compressMaxEdge(file) {
    var size = file && file.size ? file.size : 0;
    if (size > 14 * 1024 * 1024) return 1280;
    if (size > 8 * 1024 * 1024) return 1600;
    return 1920;
}

function compressImageFile(file, maxEdge) {
    maxEdge = maxEdge || compressMaxEdge(file);
    var qualities = [0.8, 0.68, 0.55];
    var target = 1.8 * 1024 * 1024;
    var hardLimit = 20 * 1024 * 1024;

    return loadImageSource(file, maxEdge).then(function(src) {
        var canvas;
        try {
            canvas = drawToCanvas(src, maxEdge);
        } finally {
            try { if (src && src.close) src.close(); } catch (e) {}
        }

        function tryQuality(i, edge) {
            return canvasToJpegBlob(canvas, qualities[i]).then(function(blob) {
                if (blob.size > target && i < qualities.length - 1) {
                    return tryQuality(i + 1, edge);
                }
                if (blob.size > target && edge > 1280) {
                    canvas = drawToCanvas(canvas, 1280);
                    return tryQuality(0, 1280);
                }
                return blob;
            });
        }

        return tryQuality(0, maxEdge).then(function(blob) {
            canvas.width = 1;
            canvas.height = 1;
            if (blob.size > hardLimit) {
                throw new Error('still too large');
            }
            var base = (file.name || 'photo').replace(/\.[a-z0-9]+$/i, '');
            return blobToUploadFile(blob, base + '.jpg', 'image/jpeg', file.lastModified);
        });
    }).catch(function(err) {
        if (maxEdge > 1024) {
            return compressImageFile(file, 1024);
        }
        return Promise.reject(err);
    });
}

function prepareImageForUpload(file) {
    if (isPdfFile(file)) {
        return Promise.resolve(file);
    }
    return readFilePrefix(file).then(function(prefix) {
        var heic = isHeicFile(file) || looksLikeHeicBytes(prefix);
        var t = (file.type || '').toLowerCase();
        var knownRaster = t === 'image/jpeg' || t === 'image/jpg' || t === 'image/pjpeg'
            || t === 'image/png' || t === 'image/webp';
        if (!heic && file.size < 700 * 1024 && knownRaster) {
            return file;
        }

        function viaHeicConvert() {
            return convertHeicIfNeeded(file).then(function(ready) {
                return compressImageFile(ready).catch(function() {
                    if (ready.size && ready.size <= 20 * 1024 * 1024) return ready;
                    return Promise.reject({ error: 'HEIC dönüştürülemedi. Kamerayı JPEG/en uyumlu moda alın veya galeriden JPEG seçin.' });
                });
            });
        }

        return compressImageFile(file).catch(function() {
            if (heic) {
                return viaHeicConvert().catch(function() {
                    return Promise.reject({ error: 'HEIC dönüştürülemedi. Kamerayı JPEG/en uyumlu moda alın veya galeriden JPEG seçin.' });
                });
            }
            if (file.size && file.size <= 20 * 1024 * 1024 && (knownRaster || !t || t === 'application/octet-stream')) {
                return file;
            }
            return Promise.reject({ error: 'Fotoğraf hazırlanamadı. Daha düşük çözünürlükte tekrar çekin.' });
        });
    });
}

function prepareUploadFiles(fileList) {
    var files = snapshotInputFiles(fileList).filter(function(f) {
        return isPdfFile(f) || isLikelyImageFile(f);
    });
    if (!files.length) {
        return Promise.reject({ error: 'Dosya seçilmedi — lütfen tekrar deneyin' });
    }
    return Promise.resolve(files);
}

function uploadLabel(file) {
    if (file && file.name) return file.name;
    if (file && file.type) return file.type.replace('image/', '').toUpperCase() + ' fotoğraf';
    return 'Fotoğraf';
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

function createUploadPreviewCard(previewEl, file, orderLabel) {
    if (!previewEl) return null;
    previewEl.style.display = '';
    var card = document.createElement('div');
    card.className = 'preview-card uploading';
    var title = document.createElement('strong');
    title.textContent = (orderLabel ? orderLabel + ' · ' : '') + uploadLabel(file);
    var status = document.createElement('span');
    status.className = 'preview-status';
    status.textContent = 'Sırada…';
    var bar = document.createElement('div');
    bar.className = 'progress-bar';
    var fill = document.createElement('div');
    fill.className = 'progress-fill';
    fill.style.width = '6%';
    bar.appendChild(fill);
    card.appendChild(title);
    card.appendChild(status);
    card.appendChild(bar);
    previewEl.prepend(card);
    return card;
}

function setCardProgress(card, percent, statusText) {
    if (!card) return;
    var fill = card.querySelector('.progress-fill');
    if (fill) {
        fill.style.width = Math.max(6, percent) + '%';
        fill.style.animation = 'none';
    }
    var status = card.querySelector('.preview-status');
    if (status && statusText) status.textContent = statusText;
}

function markCardDone(card, success, message) {
    if (!card) return;
    card.classList.remove('uploading');
    card.classList.add(success ? 'success' : 'error');
    var status = card.querySelector('.preview-status');
    if (status) status.textContent = message;
}

function appendFileToForm(formData, file, index) {
    var uploadName = (file && file.name) ? file.name : ('photo_' + (index + 1) + '.jpg');
    if (isPdfFile(file) || /\.pdf$/i.test(uploadName)) {
        if (!/\.pdf$/i.test(uploadName)) {
            uploadName = 'belge_' + (index + 1) + '.pdf';
        }
        formData.append('files[]', file, uploadName);
        return;
    }
    if (!/\.(jpe?g|png|webp)$/i.test(uploadName)) {
        uploadName = 'photo_' + (index + 1) + '.jpg';
    }
    formData.append('files[]', file, uploadName);
}

function uploadDocuments(opts) {
    opts = opts || {};
    var fileId = typeof opts.getFileId === 'function' ? opts.getFileId() : opts.fileId;
    var previewEl = opts.previewEl || null;
    var uploadUrl = opts.uploadUrl || '/api/upload.php';
    var maxFiles = opts.maxFiles || 20;

    if (!fileId) {
        return Promise.reject({ error: opts.noFileMessage || 'Önce dosyayı oluşturun' });
    }

    return prepareUploadFiles(opts.files).then(function(files) {
        if (files.length > maxFiles) {
            return Promise.reject({ error: 'En fazla ' + maxFiles + ' fotoğraf seçilebilir' });
        }

        // İlerleme çubuklarını HEMEN göster (HEIC/dönüşüm beklemeden)
        var cards = files.map(function(file, i) {
            return createUploadPreviewCard(previewEl, file, (i + 1) + '/' + files.length);
        });

        var uploaded = [];
        var errors = [];
        var index = 0;

        function uploadNext() {
            if (index >= files.length) {
                if (!uploaded.length) {
                    return Promise.reject({
                        error: errors.length ? errors.join(' · ') : 'Dosya yüklenemedi'
                    });
                }
                return { ok: true, uploaded: uploaded, errors: errors };
            }

            var sourceFile = files[index];
            var card = cards[index];
            var current = index + 1;
            var total = files.length;
            index += 1;

            setCardProgress(card, 8, current + '/' + total + ' küçültülüyor…');

            return prepareImageForUpload(sourceFile).then(function(file) {
                if (!isPdfFile(file) && file.size > 20 * 1024 * 1024) {
                    return Promise.reject({ error: 'Fotoğraf 20MB üzerinde kaldı' });
                }
                setCardProgress(card, 15, current + '/' + total + ' yükleniyor…');
                var formData = new FormData();
                formData.append('damage_file_id', String(fileId));
                formData.append('category', opts.category || 'hasar_foto');
                appendFileToForm(formData, file, current);

                return apiUpload(uploadUrl, formData, function(percent) {
                    setCardProgress(
                        card,
                        percent,
                        current + '/' + total + (percent >= 100 ? ' kaydediliyor…' : (' %' + percent))
                    );
                });
            }).then(function(data) {
                var batch = (data && data.uploaded) ? data.uploaded : [];
                uploaded = uploaded.concat(batch);
                if (data && data.errors && data.errors.length) {
                    errors = errors.concat(data.errors);
                }
                markCardDone(card, true, 'Yüklendi');
                return uploadNext();
            }).catch(function(err) {
                var message = (err && err.error) ? err.error : 'Yükleme hatası';
                errors.push(uploadLabel(sourceFile) + ': ' + message);
                markCardDone(card, false, message);
                return uploadNext();
            });
        }

        return uploadNext().then(function(result) {
            if (result.errors && result.errors.length) {
                showToast(result.errors.join(' · '), 'error');
            }
            return result;
        });
    }).catch(function(err) {
        if (previewEl) {
            markPreviewDone(previewEl, false, (err && err.error) || 'Yükleme hatası');
        }
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
        showToast(files.length + ' fotoğraf seçildi, yükleniyor…', 'success');
        return uploadDocuments(Object.assign({}, opts, { category: category, files: files }))
            .then(function(data) {
                showToast(((data.uploaded && data.uploaded.length) || 0) + ' evrak yüklendi', 'success');
                if (opts.reloadOnSuccess) {
                    setTimeout(function() { location.reload(); }, 900);
                }
            })
            .catch(function(err) {
                showToast((err && err.error) || 'Yükleme hatası', 'error');
            });
    }).catch(function(err) {
        showToast((err && err.error) || 'Dosya okunamadı', 'error');
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

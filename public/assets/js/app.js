function getCsrfToken() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : '';
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

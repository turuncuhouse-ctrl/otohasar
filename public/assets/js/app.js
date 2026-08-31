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
    fetch('/api/whatsapp_log.php', { method: 'POST', body: formData }).catch(function() {});
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

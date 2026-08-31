<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
$pageTitle = 'Pano';
$activeNav = 'dashboard';

$pdo = db();
$scope = $_GET['scope'] ?? ($currentUser['role'] === 'advisor' ? 'mine' : 'all');
if ($currentUser['role'] !== 'advisor') {
    $scope = 'all';
}

$sql = "SELECT df.id, df.file_number, df.status, df.insurance_company, df.created_at, df.advisor_id,
               v.plate, v.brand, v.model,
               c.name AS customer_name, c.phone AS customer_phone,
               u.name AS advisor_name,
               (SELECT COUNT(*) FROM file_documents fd WHERE fd.damage_file_id = df.id) AS doc_count
        FROM damage_files df
        JOIN vehicles v ON v.id = df.vehicle_id
        JOIN customers c ON c.id = v.customer_id
        JOIN users u ON u.id = df.advisor_id";

$params = [];
if ($scope === 'mine') {
    $sql .= ' WHERE df.advisor_id = ?';
    $params[] = $currentUser['id'];
}
$sql .= ' ORDER BY df.updated_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$files = $stmt->fetchAll();

$columns = status_labels();
$board = [];
foreach (array_keys($columns) as $status) {
    $board[$status] = [];
}
foreach ($files as $file) {
    $board[$file['status']][] = $file;
}

$activeCount = 0;
foreach ($files as $file) {
    if ($file['status'] !== 'tamamlandi') {
        $activeCount++;
    }
}

$advisorWorkload = [];
if ($currentUser['role'] === 'manager') {
    $stmt = $pdo->query(
        "SELECT u.name, u.id, COUNT(df.id) AS file_count
         FROM users u
         LEFT JOIN damage_files df ON df.advisor_id = u.id AND df.status != 'tamamlandi'
         WHERE u.role = 'advisor' AND u.is_active = 1
         GROUP BY u.id
         ORDER BY file_count DESC"
    );
    $advisorWorkload = $stmt->fetchAll();
}

function render_file_row(array $card): string
{
    $wa = wa_button_html(
        $card['customer_phone'] ?? null,
        $card['customer_name'],
        $card['plate'],
        $card['file_number'],
        $card['status'],
        (int) $card['id']
    );
    $statusLabel = status_labels()[$card['status']] ?? $card['status'];
    $color = status_colors()[$card['status']] ?? '';

    return '<article class="file-row" data-id="' . (int)$card['id'] . '" data-status="' . e($card['status']) . '">'
        . '<a class="file-row-main" href="/file.php?id=' . (int)$card['id'] . '">'
        . plate_badge_html($card['plate'])
        . '<div class="file-row-body">'
        . '<div class="file-row-top">'
        . '<span class="card-file-no">' . e($card['file_number']) . '</span>'
        . '<span class="status-pill small ' . e($color) . '">' . e($statusLabel) . '</span>'
        . '</div>'
        . '<div class="file-row-vehicle">' . e($card['brand'] . ' ' . $card['model']) . '</div>'
        . '<div class="file-row-meta">' . e($card['customer_name'])
        . ' · 📎 ' . (int)$card['doc_count']
        . ( $card['insurance_company'] ? ' · ' . e($card['insurance_company']) : '' )
        . '</div>'
        . '</div></a>'
        . '<div class="file-row-actions">' . $wa
        . '<a class="btn btn-sm btn-ghost" href="/file.php?id=' . (int)$card['id'] . '">Aç</a>'
        . '</div></article>';
}

require __DIR__ . '/../includes/header.php';
?>

<div class="dash-hero">
    <div>
        <p class="dash-hello">Merhaba, <?= e($currentUser['name']) ?></p>
        <h1><?= $scope === 'mine' ? 'Dosyalarım' : 'Hasar Dosya Panosu' ?></h1>
        <p class="dash-sub">
            <?= $activeCount ?> aktif dosya
            <?php if ($scope === 'mine'): ?> · yalnızca sizin dosyalarınız<?php endif; ?>
        </p>
    </div>
    <?php if ($currentUser['role'] !== 'workshop'): ?>
    <a href="/new-file.php" class="btn btn-primary">+ Yeni Dosya</a>
    <?php endif; ?>
</div>

<?php if ($currentUser['role'] === 'advisor'): ?>
<div class="scope-toggle">
    <a class="scope-link<?= $scope === 'mine' ? ' active' : '' ?>" href="/dashboard.php?scope=mine">Benim dosyalarım</a>
    <a class="scope-link<?= $scope === 'all' ? ' active' : '' ?>" href="/dashboard.php?scope=all">Tüm dosyalar</a>
</div>
<?php endif; ?>

<?php if ($currentUser['role'] === 'manager' && $advisorWorkload): ?>
<div class="workload-chips">
    <span class="workload-label">Danışman iş yükü</span>
    <?php foreach ($advisorWorkload as $aw): ?>
    <span class="workload-chip"><?= e($aw['name']) ?>: <strong><?= (int)$aw['file_count'] ?></strong></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="stat-grid" id="statGrid">
    <button type="button" class="stat-card active" data-filter="all">
        <span class="stat-num"><?= count($files) ?></span>
        <span class="stat-label">Toplam</span>
    </button>
    <?php foreach ($columns as $status => $label): ?>
    <button type="button" class="stat-card <?= e(status_colors()[$status]) ?>" data-filter="<?= e($status) ?>">
        <span class="stat-num"><?= count($board[$status]) ?></span>
        <span class="stat-label"><?= e($label) ?></span>
    </button>
    <?php endforeach; ?>
</div>

<div class="view-toolbar">
    <span class="view-hint" id="filterHint">Tüm dosyalar</span>
    <div class="view-switch">
        <button type="button" class="view-btn active" data-view="list">Liste</button>
        <button type="button" class="view-btn" data-view="board">Pano</button>
    </div>
</div>

<div class="file-list-view" id="listView">
    <?php foreach ($columns as $status => $label):
        if (empty($board[$status])) continue;
    ?>
    <section class="status-group" data-status="<?= e($status) ?>">
        <h2 class="status-group-title <?= e(status_colors()[$status]) ?>">
            <?= e($label) ?>
            <span><?= count($board[$status]) ?></span>
        </h2>
        <?php foreach ($board[$status] as $card): ?>
            <?= render_file_row($card) ?>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
    <?php if (empty($files)): ?>
    <p class="empty-state">Henüz hasar dosyası yok.</p>
    <?php endif; ?>
</div>

<div class="kanban-board hidden" id="kanbanBoard">
    <?php foreach ($columns as $status => $label): ?>
    <div class="kanban-column" data-status="<?= e($status) ?>">
        <div class="kanban-header <?= e(status_colors()[$status]) ?>">
            <span class="kanban-title"><?= e($label) ?></span>
            <span class="kanban-count"><?= count($board[$status]) ?></span>
        </div>
        <div class="kanban-cards" data-status="<?= e($status) ?>">
            <?php foreach ($board[$status] as $card): ?>
            <div class="kanban-card" draggable="true" data-id="<?= (int)$card['id'] ?>" data-status="<?= e($card['status']) ?>">
                <a href="/file.php?id=<?= (int)$card['id'] ?>" class="card-link">
                    <?= plate_badge_html($card['plate']) ?>
                    <div class="card-file-no"><?= e($card['file_number']) ?></div>
                    <div class="card-vehicle"><?= e($card['brand'] . ' ' . $card['model']) ?></div>
                    <div class="card-customer"><?= e($card['customer_name']) ?></div>
                    <div class="card-meta">
                        <span class="card-docs">📎 <?= (int)$card['doc_count'] ?></span>
                        <span class="card-insurance"><?= e($card['insurance_company']) ?></span>
                    </div>
                    <div class="card-advisor"><?= e($card['advisor_name']) ?></div>
                </a>
                <?= wa_button_html($card['customer_phone'] ?? null, $card['customer_name'], $card['plate'], $card['file_number'], $card['status'], (int)$card['id']) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
(function() {
    var csrfEl = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfEl ? csrfEl.content : '';
    var userRole = <?= json_encode($currentUser['role']) ?>;
    var dragged = null;
    var filter = 'all';

    document.getElementById('statGrid').addEventListener('click', function(e) {
        var btn = e.target.closest('.stat-card');
        if (!btn) return;
        filter = btn.dataset.filter;
        document.querySelectorAll('.stat-card').forEach(function(c) { c.classList.remove('active'); });
        btn.classList.add('active');
        applyFilter();
    });

    function applyFilter() {
        var hint = document.getElementById('filterHint');
        var labels = <?= json_encode($columns, JSON_UNESCAPED_UNICODE) ?>;
        hint.textContent = filter === 'all' ? 'Tüm dosyalar' : (labels[filter] || filter);

        document.querySelectorAll('.status-group').forEach(function(g) {
            g.style.display = (filter === 'all' || g.dataset.status === filter) ? '' : 'none';
        });
        document.querySelectorAll('.kanban-column').forEach(function(col) {
            col.style.display = (filter === 'all' || col.dataset.status === filter) ? '' : 'none';
        });
        document.querySelectorAll('.file-row, .kanban-card').forEach(function(row) {
            if (filter === 'all' || row.dataset.status === filter) {
                row.classList.remove('is-hidden');
            } else {
                row.classList.add('is-hidden');
            }
        });
    }

    document.querySelectorAll('.view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.view-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var list = document.getElementById('listView');
            var board = document.getElementById('kanbanBoard');
            if (this.dataset.view === 'board') {
                list.classList.add('hidden');
                board.classList.remove('hidden');
            } else {
                list.classList.remove('hidden');
                board.classList.add('hidden');
            }
        });
    });

    document.querySelectorAll('.kanban-card').forEach(function(card) {
        card.addEventListener('dragstart', function(e) {
            if (e.target.closest('.btn-wa')) { e.preventDefault(); return; }
            dragged = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            dragged = null;
        });
    });

    document.querySelectorAll('.kanban-cards').forEach(function(col) {
        col.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        col.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        col.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            if (!dragged) return;

            var newStatus = this.dataset.status;
            var oldStatus = dragged.dataset.status;
            var fileId = dragged.dataset.id;
            if (newStatus === oldStatus) return;

            if (userRole === 'workshop') {
                var allowed = (oldStatus === 'onarimda' && newStatus === 'teslime_hazir') ||
                              (oldStatus === 'teslime_hazir' && newStatus === 'onarimda');
                if (!allowed) {
                    showToast('Atölye personeli yalnızca Onarımda ↔ Teslime Hazır geçişi yapabilir', 'error');
                    return;
                }
            }

            var formData = new FormData();
            formData.append('csrf', csrf);
            formData.append('damage_file_id', fileId);
            formData.append('status', newStatus);

            fetch('/api/status.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        this.appendChild(dragged);
                        dragged.dataset.status = newStatus;
                        updateCounts();
                        showToast('Durum güncellendi', 'success');
                        offerWhatsApp(data.whatsapp, data.plate);
                    } else {
                        showToast(data.error || 'Hata oluştu', 'error');
                    }
                }.bind(this))
                .catch(function() { showToast('Bağlantı hatası', 'error'); });
        });
    });

    function updateCounts() {
        document.querySelectorAll('.kanban-column').forEach(function(col) {
            var count = col.querySelectorAll('.kanban-card').length;
            col.querySelector('.kanban-count').textContent = count;
        });
    }

    function offerWhatsApp(url, plate) {
        if (!url) return;
        showWaPrompt(url, plate);
    }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

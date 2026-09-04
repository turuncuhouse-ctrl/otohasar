<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Duyurular';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'active', 'passive'], true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $body = trim($_POST['body'] ?? '');
            $link = trim($_POST['link_url'] ?? '') ?: null;
            $starts = trim($_POST['starts_at'] ?? '');
            $ends = trim($_POST['ends_at'] ?? '');
            $sort = (int) ($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;

            $startsAt = $starts !== '' ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $starts)) ?: time()) : null;
            $endsAt = $ends !== '' ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $ends)) ?: time()) : null;

            if ($body === '') {
                $error = 'Duyuru metni zorunlu';
            } elseif ($startsAt && $endsAt && $endsAt < $startsAt) {
                $error = 'Bitiş, başlangıçtan önce olamaz';
            } elseif ($id > 0) {
                $pdo->prepare(
                    'UPDATE app_announcements SET body=?, link_url=?, starts_at=?, ends_at=?, sort_order=?, is_active=? WHERE id=?'
                )->execute([$body, $link, $startsAt, $endsAt, $sort, $active, $id]);
                $message = 'Duyuru güncellendi';
            } else {
                $pdo->prepare(
                    'INSERT INTO app_announcements (body, link_url, starts_at, ends_at, sort_order, is_active) VALUES (?,?,?,?,?,?)'
                )->execute([$body, $link, $startsAt, $endsAt, $sort, $active]);
                $message = 'Duyuru eklendi';
            }
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE app_announcements SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
            $message = 'Duyuru durumu güncellendi';
        }
    }
}

$rows = announcements_all(false);
if ($statusFilter === 'active') {
    $rows = array_values(array_filter($rows, static fn($r) => !empty($r['is_active'])));
} elseif ($statusFilter === 'passive') {
    $rows = array_values(array_filter($rows, static fn($r) => empty($r['is_active'])));
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach (announcements_all(false) as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
        break;
    }
}

$startsVal = '';
$endsVal = '';
if ($edit && !empty($edit['starts_at'])) {
    $startsVal = date('Y-m-d\TH:i', strtotime((string) $edit['starts_at']) ?: time());
}
if ($edit && !empty($edit['ends_at'])) {
    $endsVal = date('Y-m-d\TH:i', strtotime((string) $edit['ends_at']) ?: time());
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Duyurular</h1>
        <p class="dash-sub">Header üzerinde kayan yazı. İsterseniz başlangıç/bitiş tarihi verin; boş bırakırsanız sürekli görünür.</p>
    </div>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-filter-bar">
    <a class="filter-chip<?= $statusFilter === 'all' ? ' active' : '' ?>" href="/admin/announcements.php">Tümü</a>
    <a class="filter-chip<?= $statusFilter === 'active' ? ' active' : '' ?>" href="/admin/announcements.php?status=active">Aktif</a>
    <a class="filter-chip<?= $statusFilter === 'passive' ? ' active' : '' ?>" href="/admin/announcements.php?status=passive">Pasif</a>
    <?php if ($edit): ?>
    <a class="btn btn-sm btn-primary" href="/admin/announcements.php?status=<?= e($statusFilter) ?>">+ Yeni duyuru</a>
    <?php endif; ?>
</div>

<div class="admin-layout admin-layout-wide">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <h2><?= $edit ? 'Duyuruyu güncelle' : 'Yeni duyuru' ?></h2>
        <div class="form-group">
            <label>Metin</label>
            <textarea class="form-input" name="body" rows="3" required maxlength="500" placeholder="Örn. Yarın 12:00–14:00 sistem bakımı yapılacaktır."><?= e($edit['body'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Link (opsiyonel)</label>
            <input class="form-input" name="link_url" value="<?= e($edit['link_url'] ?? '') ?>" placeholder="https://...">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Başlangıç (boş = hemen)</label>
                <input class="form-input" type="datetime-local" name="starts_at" value="<?= e($startsVal) ?>">
            </div>
            <div class="form-group">
                <label>Bitiş (boş = süresiz)</label>
                <input class="form-input" type="datetime-local" name="ends_at" value="<?= e($endsVal) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Sıra</label>
            <input class="form-input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
        </div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$edit || !empty($edit['is_active']) ? 'checked' : '' ?>> Aktif</label>
        <button class="btn btn-primary btn-block" type="submit"><?= $edit ? 'Kaydet' : 'Yayınla' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost btn-block" href="/admin/announcements.php?status=<?= e($statusFilter) ?>">İptal</a><?php endif; ?>
    </form>

    <div class="admin-card-list">
        <?php if (!$rows): ?>
        <div class="admin-empty">Duyuru yok.</div>
        <?php endif; ?>
        <?php foreach ($rows as $r):
            $nowOk = (empty($r['starts_at']) || strtotime((string)$r['starts_at']) <= time())
                && (empty($r['ends_at']) || strtotime((string)$r['ends_at']) >= time());
            $live = !empty($r['is_active']) && $nowOk;
        ?>
        <article class="mgmt-card<?= empty($r['is_active']) ? ' is-passive' : '' ?>">
            <div class="mgmt-card-main">
                <div class="mgmt-card-title">
                    <strong><?= e(mb_strimwidth($r['body'], 0, 80, '…')) ?></strong>
                    <span class="status-pill small <?= $live ? 'status-green' : 'status-slate' ?>"><?= $live ? 'Yayında' : (!empty($r['is_active']) ? 'Zamanlı' : 'Pasif') ?></span>
                </div>
                <div class="mgmt-card-meta">
                    <span><?= $r['starts_at'] ? e(format_datetime_short($r['starts_at'])) : 'Başlangıç: hemen' ?></span>
                    <span><?= $r['ends_at'] ? e(format_datetime_short($r['ends_at'])) : 'Bitiş: süresiz' ?></span>
                </div>
            </div>
            <div class="mgmt-card-actions">
                <a class="btn btn-sm btn-ghost" href="/admin/announcements.php?status=<?= e($statusFilter) ?>&edit=<?= (int)$r['id'] ?>">Düzenle</a>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit"><?= !empty($r['is_active']) ? 'Pasife al' : 'Aktifleştir' ?></button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Kullanıcı Grupları';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';
$catalog = permission_catalog();
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'active', 'passive'], true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create' || $action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = strtolower(trim($_POST['code'] ?? ''));
            $code = preg_replace('/[^a-z0-9_]/', '', $code) ?? '';
            $sort = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $perms = array_values(array_intersect(array_keys($_POST['perms'] ?? []), all_permission_keys()));

            if ($name === '' || ($action === 'create' && $code === '')) {
                $error = 'Grup adı ve kod zorunlu';
            } else {
                try {
                    if ($action === 'create') {
                        $pdo->prepare(
                            'INSERT INTO user_groups (code, name, sort_order, is_system, is_active) VALUES (?,?,?,0,?)'
                        )->execute([$code, $name, $sort, $isActive]);
                        $id = (int) $pdo->lastInsertId();
                        set_group_permissions($id, $perms);
                        $message = 'Grup oluşturuldu';
                        header('Location: /admin/groups.php?edit=' . $id . '&ok=1');
                        exit;
                    }
                    $g = user_group_by_id($id);
                    if (!$g) {
                        $error = 'Grup bulunamadı';
                    } else {
                        $pdo->prepare(
                            'UPDATE user_groups SET name=?, sort_order=?, is_active=? WHERE id=?'
                        )->execute([$name, $sort, $isActive, $id]);
                        set_group_permissions($id, $perms);
                        $message = 'Grup güncellendi';
                    }
                } catch (Throwable $e) {
                    $error = 'Kayıt hatası (kod benzersiz olmalı)';
                }
            }
        } elseif ($action === 'toggle_active') {
            $id = (int) ($_POST['id'] ?? 0);
            $g = user_group_by_id($id);
            if (!$g) {
                $error = 'Grup bulunamadı';
            } elseif ((int) $g['is_system'] === 1 && ($g['code'] ?? '') === 'admin' && !is_system_founder($currentUser)) {
                $error = 'Sistem Admin grubunu yalnızca kurucu pasife alabilir';
            } else {
                $pdo->prepare('UPDATE user_groups SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
                $message = 'Grup durumu güncellendi';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $message = 'Grup kaydedildi';
}

$groups = user_groups(false);
if ($statusFilter === 'active') {
    $groups = array_values(array_filter($groups, static fn($g) => !empty($g['is_active'])));
} elseif ($statusFilter === 'passive') {
    $groups = array_values(array_filter($groups, static fn($g) => empty($g['is_active'])));
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach (user_groups(false) as $g) {
    if ((int) $g['id'] === $editId) {
        $edit = $g;
        break;
    }
}
$editPerms = $edit ? group_permission_map((int) $edit['id']) : [];

$userCounts = [];
try {
    foreach ($pdo->query('SELECT group_id, COUNT(*) AS c FROM users WHERE group_id IS NOT NULL GROUP BY group_id') as $row) {
        $userCounts[(int) $row['group_id']] = (int) $row['c'];
    }
} catch (Throwable $e) {
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Kullanıcı Grupları</h1>
        <p class="dash-sub">Yeni grup açın, izinleri güncelleyin. Silme yok — aktif/pasif kullanın.</p>
    </div>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-filter-bar">
    <a class="filter-chip<?= $statusFilter === 'all' ? ' active' : '' ?>" href="/admin/groups.php">Tümü</a>
    <a class="filter-chip<?= $statusFilter === 'active' ? ' active' : '' ?>" href="/admin/groups.php?status=active">Aktif</a>
    <a class="filter-chip<?= $statusFilter === 'passive' ? ' active' : '' ?>" href="/admin/groups.php?status=passive">Pasif</a>
    <?php if ($edit): ?>
    <a class="btn btn-sm btn-primary" href="/admin/groups.php?status=<?= e($statusFilter) ?>">+ Yeni grup</a>
    <?php endif; ?>
</div>

<div class="admin-layout admin-layout-wide">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <h2><?= $edit ? 'Grubu güncelle' : 'Yeni grup' ?></h2>
        <p class="form-hint">Hasar / Prim / Rapor / Tanıtım izinlerini işaretleyin. Sadece prim için hasarı kapatın.</p>
        <div class="form-group">
            <label>Grup Adı</label>
            <input class="form-input" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Örn. Prim Satış Ekibi">
        </div>
        <div class="form-group">
            <label>Kod <?= $edit ? '(değiştirilemez)' : '' ?></label>
            <?php if ($edit): ?>
            <input class="form-input" value="<?= e($edit['code']) ?>" disabled>
            <?php else: ?>
            <input class="form-input" name="code" required pattern="[a-z0-9_]+" placeholder="prim_satis_ekibi">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Sıra</label>
            <input class="form-input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 100) ?>">
        </div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$edit || !empty($edit['is_active']) ? 'checked' : '' ?>> Aktif grup</label>

        <div class="perm-matrix">
            <?php foreach ($catalog as $section => $items): ?>
            <div class="perm-section">
                <h3><?= e($section) ?></h3>
                <?php foreach ($items as $key => $label): ?>
                <label class="check-row">
                    <input type="checkbox" name="perms[<?= e($key) ?>]" value="1"
                        <?= !empty($editPerms[$key]) ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="btn btn-primary btn-block" type="submit"><?= $edit ? 'Güncelle' : 'Oluştur' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost btn-block" href="/admin/groups.php?status=<?= e($statusFilter) ?>">İptal</a><?php endif; ?>
    </form>

    <div class="admin-card-list">
        <?php if (!$groups): ?>
        <div class="admin-empty">Bu filtrede grup yok.</div>
        <?php endif; ?>
        <?php foreach ($groups as $g):
            $permCount = count(array_filter(group_permission_map((int)$g['id'])));
            $uc = $userCounts[(int)$g['id']] ?? 0;
        ?>
        <article class="mgmt-card<?= empty($g['is_active']) ? ' is-passive' : '' ?>">
            <div class="mgmt-card-main">
                <div class="mgmt-card-title">
                    <strong><?= e($g['name']) ?></strong>
                    <span class="status-pill small <?= !empty($g['is_active']) ? 'status-green' : 'status-slate' ?>"><?= !empty($g['is_active']) ? 'Aktif' : 'Pasif' ?></span>
                    <?php if ((int)$g['is_system'] === 1): ?><span class="status-pill small status-blue">Sistem</span><?php endif; ?>
                </div>
                <div class="mgmt-card-meta">
                    <span><code><?= e($g['code']) ?></code></span>
                    <span><?= $uc ?> kullanıcı</span>
                    <span><?= $permCount ?> izin</span>
                </div>
            </div>
            <div class="mgmt-card-actions">
                <a class="btn btn-sm btn-ghost" href="/admin/groups.php?status=<?= e($statusFilter) ?>&edit=<?= (int)$g['id'] ?>">Düzenle</a>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                    <button class="btn btn-sm <?= !empty($g['is_active']) ? 'btn-ghost' : 'btn-primary' ?>" type="submit">
                        <?= !empty($g['is_active']) ? 'Pasife al' : 'Aktifleştir' ?>
                    </button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

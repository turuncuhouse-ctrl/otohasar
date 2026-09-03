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
                    } else {
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
                    }
                } catch (Throwable $e) {
                    $error = 'Kayıt hatası (kod benzersiz olmalı)';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $g = user_group_by_id($id);
            if (!$g) {
                $error = 'Grup bulunamadı';
            } elseif ((int) $g['is_system'] === 1) {
                $error = 'Sistem grupları silinemez';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE group_id=?');
                $stmt->execute([$id]);
                $cnt = (int) $stmt->fetchColumn();
                if ($cnt > 0) {
                    $error = 'Bu gruba bağlı kullanıcı var; önce kullanıcıları taşıyın';
                } else {
                    $pdo->prepare('DELETE FROM user_groups WHERE id=?')->execute([$id]);
                    $message = 'Grup silindi';
                }
            }
        }
    }
}

$groups = user_groups(false);
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($groups as $g) {
    if ((int) $g['id'] === $editId) {
        $edit = $g;
        break;
    }
}
$editPerms = $edit ? group_permission_map((int) $edit['id']) : [];

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Kullanıcı Grupları</h1>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <h2><?= $edit ? 'Grup Düzenle' : 'Yeni Grup' ?></h2>
        <p class="form-hint">İzinleri işaretleyerek grubun hasar, prim ve rapor erişimini belirleyin. Sadece prim için hasar işaretlerini kapatın.</p>
        <div class="form-group">
            <label>Grup Adı</label>
            <input class="form-input" name="name" required value="<?= e($edit['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Kod <?= $edit ? '(değiştirilemez)' : '' ?></label>
            <?php if ($edit): ?>
            <input class="form-input" value="<?= e($edit['code']) ?>" disabled>
            <?php else: ?>
            <input class="form-input" name="code" required pattern="[a-z0-9_]+" placeholder="ornek_grup">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Sıra</label>
            <input class="form-input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 100) ?>">
        </div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$edit || !empty($edit['is_active']) ? 'checked' : '' ?>> Aktif</label>

        <div class="perm-matrix">
            <?php foreach ($catalog as $section => $items): ?>
            <div class="perm-section">
                <h3><?= e($section) ?></h3>
                <?php foreach ($items as $key => $label): ?>
                <label class="check-row">
                    <input type="checkbox" name="perms[<?= e($key) ?>]" value="1"
                        <?= !empty($editPerms[$key]) ? 'checked' : '' ?>>
                    <?= e($label) ?>
                    <span class="perm-key"><?= e($key) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="btn btn-primary btn-block" type="submit"><?= $edit ? 'Kaydet' : 'Oluştur' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost btn-block" href="/admin/groups.php">İptal</a><?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Grup</th><th>Kod</th><th>Sistem</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($groups as $g): ?>
            <tr>
                <td><?= e($g['name']) ?></td>
                <td><code><?= e($g['code']) ?></code></td>
                <td><?= (int)$g['is_system'] === 1 ? 'Evet' : 'Hayır' ?></td>
                <td><?= $g['is_active'] ? 'Aktif' : 'Pasif' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-ghost" href="/admin/groups.php?edit=<?= (int)$g['id'] ?>">Düzenle</a>
                    <?php if ((int)$g['is_system'] !== 1): ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

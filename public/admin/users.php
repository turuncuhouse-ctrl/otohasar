<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Kullanıcılar';
$activeNav = 'admin';
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create' || $action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'advisor';
            $password = $_POST['password'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (!in_array($role, ['admin', 'manager', 'advisor', 'workshop'], true)) {
                $error = 'Geçersiz rol';
            } elseif ($name === '' || $username === '' || $email === '') {
                $error = 'Ad, kullanıcı adı ve e-posta zorunlu';
            } elseif ($action === 'create' && strlen($password) < 4) {
                $error = 'Şifre en az 4 karakter olmalı';
            } else {
                try {
                    if ($action === 'create') {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $pdo->prepare(
                            'INSERT INTO users (name, username, role, email, phone, password, is_active) VALUES (?,?,?,?,?,?,?)'
                        )->execute([$name, $username, $role, $email, $phone ?: null, $hash, $isActive]);
                        $message = 'Kullanıcı oluşturuldu';
                    } else {
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $pdo->prepare(
                                'UPDATE users SET name=?, username=?, role=?, email=?, phone=?, password=?, is_active=? WHERE id=?'
                            )->execute([$name, $username, $role, $email, $phone ?: null, $hash, $isActive, $id]);
                        } else {
                            $pdo->prepare(
                                'UPDATE users SET name=?, username=?, role=?, email=?, phone=?, is_active=? WHERE id=?'
                            )->execute([$name, $username, $role, $email, $phone ?: null, $isActive, $id]);
                        }
                        $message = 'Kullanıcı güncellendi';
                    }
                } catch (Throwable $e) {
                    $error = 'Kayıt hatası (kullanıcı adı/e-posta benzersiz olmalı)';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $currentUser['id']) {
                $error = 'Kendi hesabınızı silemezsiniz';
            } else {
                try {
                    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                    $message = 'Kullanıcı silindi';
                } catch (Throwable $e) {
                    $error = 'Silinemedi (dosya bağlantısı olabilir). Pasife alın.';
                }
            }
        }
    }
}

$users = $pdo->query('SELECT id, name, username, role, email, phone, is_active, created_at FROM users ORDER BY id')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
foreach ($users as $u) {
    if ((int) $u['id'] === $editId) {
        $editUser = $u;
        break;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Kullanıcı Yönetimi</h1>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Yönetim</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
        <h2><?= $editUser ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı' ?></h2>
        <div class="form-group"><label>Ad Soyad</label><input class="form-input" name="name" required value="<?= e($editUser['name'] ?? '') ?>"></div>
        <div class="form-group"><label>Kullanıcı Adı</label><input class="form-input" name="username" required value="<?= e($editUser['username'] ?? '') ?>"></div>
        <div class="form-group"><label>E-posta</label><input class="form-input" type="email" name="email" required value="<?= e($editUser['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Telefon</label><input class="form-input" name="phone" value="<?= e($editUser['phone'] ?? '') ?>"></div>
        <div class="form-group">
            <label>Rol</label>
            <select class="form-input" name="role">
                <?php foreach (role_labels() as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= ($editUser['role'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Şifre <?= $editUser ? '(boş bırakırsanız değişmez)' : '*' ?></label>
            <input class="form-input" type="password" name="password" <?= $editUser ? '' : 'required' ?> minlength="4" autocomplete="new-password">
        </div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$editUser || !empty($editUser['is_active']) ? 'checked' : '' ?>> Aktif</label>
        <button class="btn btn-primary btn-block" type="submit"><?= $editUser ? 'Kaydet' : 'Oluştur' ?></button>
        <?php if ($editUser): ?><a class="btn btn-ghost btn-block" href="/admin/users.php">İptal</a><?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Ad</th><th>Kullanıcı</th><th>Rol</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e(role_labels()[$u['role']] ?? $u['role']) ?></td>
                <td><?= $u['is_active'] ? 'Aktif' : 'Pasif' ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-ghost" href="/admin/users.php?edit=<?= (int)$u['id'] ?>">Düzenle</a>
                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
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

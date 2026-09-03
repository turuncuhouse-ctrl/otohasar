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
$groups = user_groups(true);
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
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phoneRaw = trim($_POST['phone'] ?? '');
            $phoneNorm = normalize_login_phone($phoneRaw);
            $phoneStore = $phoneNorm !== '' ? $phoneNorm : null;
            $groupId = (int) ($_POST['group_id'] ?? 0);
            $password = $_POST['password'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $group = user_group_by_id($groupId);
            if (!$group) {
                $error = 'Geçersiz grup';
            } elseif ($name === '' || $username === '' || $email === '') {
                $error = 'Ad, kullanıcı adı ve e-posta zorunlu';
            } elseif ($action === 'create' && strlen($password) < 4) {
                $error = 'Şifre en az 4 karakter olmalı';
            } else {
                if ($phoneStore) {
                    $chk = $pdo->prepare('SELECT id, name FROM users WHERE id != ? AND phone IS NOT NULL');
                    $chk->execute([$action === 'update' ? $id : 0]);
                    foreach ($chk->fetchAll() as $row) {
                        if (normalize_login_phone((string) $row['phone']) === $phoneStore) {
                            $error = 'Bu telefon başka kullanıcıda kayıtlı: ' . $row['name'];
                            break;
                        }
                    }
                }
            }

            if ($error === '') {
                $role = legacy_role_for_group_code((string) $group['code']);
                try {
                    if ($action === 'create') {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $pdo->prepare(
                            'INSERT INTO users (name, username, role, group_id, email, phone, password, is_active) VALUES (?,?,?,?,?,?,?,?)'
                        )->execute([$name, $username, $role, $groupId, $email, $phoneStore, $hash, $isActive]);
                        $message = 'Kullanıcı oluşturuldu';
                    } else {
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $pdo->prepare(
                                'UPDATE users SET name=?, username=?, role=?, group_id=?, email=?, phone=?, password=?, is_active=? WHERE id=?'
                            )->execute([$name, $username, $role, $groupId, $email, $phoneStore, $hash, $isActive, $id]);
                        } else {
                            $pdo->prepare(
                                'UPDATE users SET name=?, username=?, role=?, group_id=?, email=?, phone=?, is_active=? WHERE id=?'
                            )->execute([$name, $username, $role, $groupId, $email, $phoneStore, $isActive, $id]);
                        }
                        $message = 'Kullanıcı güncellendi';
                    }
                } catch (Throwable $e) {
                    $error = 'Kayıt hatası (kullanıcı adı/e-posta benzersiz olmalı)';
                }
            }
        } elseif ($action === 'toggle_active') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $currentUser['id']) {
                $error = 'Kendi hesabınızı pasife alamazsınız';
            } else {
                $pdo->prepare('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
                $message = 'Kullanıcı durumu güncellendi';
            }
        }
    }
}

$sql = 'SELECT u.id, u.name, u.username, u.role, u.group_id, u.email, u.phone, u.is_active, u.created_at,
               g.name AS group_name
        FROM users u
        LEFT JOIN user_groups g ON g.id = u.group_id';
if ($statusFilter === 'active') {
    $sql .= ' WHERE u.is_active = 1';
} elseif ($statusFilter === 'passive') {
    $sql .= ' WHERE u.is_active = 0';
}
$sql .= ' ORDER BY u.is_active DESC, u.name';
$users = $pdo->query($sql)->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
foreach ($users as $u) {
    if ((int) $u['id'] === $editId) {
        $editUser = $u;
        break;
    }
}
if ($editId && !$editUser) {
    $stmt = $pdo->prepare(
        'SELECT u.*, g.name AS group_name FROM users u LEFT JOIN user_groups g ON g.id = u.group_id WHERE u.id=?'
    );
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch() ?: null;
}

$phoneDisplay = '';
if ($editUser && !empty($editUser['phone'])) {
    $n = normalize_login_phone((string) $editUser['phone']);
    $phoneDisplay = (strlen($n) === 12 && str_starts_with($n, '90')) ? substr($n, 2) : $n;
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Kullanıcı Yönetimi</h1>
        <p class="dash-sub">Giriş: kullanıcı adı veya telefon. Silme yok — rapor bütünlüğü için aktif/pasif kullanın.</p>
    </div>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-filter-bar">
    <a class="filter-chip<?= $statusFilter === 'all' ? ' active' : '' ?>" href="/admin/users.php">Tümü</a>
    <a class="filter-chip<?= $statusFilter === 'active' ? ' active' : '' ?>" href="/admin/users.php?status=active">Aktif</a>
    <a class="filter-chip<?= $statusFilter === 'passive' ? ' active' : '' ?>" href="/admin/users.php?status=passive">Pasif</a>
    <?php if ($editUser): ?>
    <a class="btn btn-sm btn-ghost" href="/admin/users.php?status=<?= e($statusFilter) ?>">+ Yeni kullanıcı</a>
    <?php endif; ?>
</div>

<div class="admin-layout admin-layout-wide">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
        <h2><?= $editUser ? 'Kullanıcıyı güncelle' : 'Yeni kullanıcı' ?></h2>
        <p class="form-hint">Yetkiler <a href="/admin/groups.php">grup</a> üzerinden gelir. Telefon ile giriş için numara kaydedin.</p>
        <div class="form-group"><label>Ad Soyad</label><input class="form-input" name="name" required value="<?= e($editUser['name'] ?? '') ?>"></div>
        <div class="form-group"><label>Kullanıcı Adı</label><input class="form-input" name="username" required value="<?= e($editUser['username'] ?? '') ?>" autocomplete="off"></div>
        <div class="form-group"><label>E-posta</label><input class="form-input" type="email" name="email" required value="<?= e($editUser['email'] ?? '') ?>"></div>
        <div class="form-group">
            <label>Telefon (giriş için)</label>
            <div class="phone-login-row">
                <span class="phone-prefix">+90</span>
                <input class="form-input phone-login-input" name="phone" inputmode="numeric" pattern="[0-9]*" maxlength="10" placeholder="5XXXXXXXXX" value="<?= e($phoneDisplay) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Grup</label>
            <select class="form-input" name="group_id" required>
                <?php foreach ($groups as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= (int)($editUser['group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Şifre <?= $editUser ? '(boş = değişmez)' : '*' ?></label>
            <input class="form-input" type="password" name="password" <?= $editUser ? '' : 'required' ?> minlength="4" autocomplete="new-password">
        </div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$editUser || !empty($editUser['is_active']) ? 'checked' : '' ?>> Aktif hesap</label>
        <button class="btn btn-primary btn-block" type="submit"><?= $editUser ? 'Kaydet' : 'Oluştur' ?></button>
        <?php if ($editUser): ?><a class="btn btn-ghost btn-block" href="/admin/users.php?status=<?= e($statusFilter) ?>">İptal</a><?php endif; ?>
    </form>

    <div class="admin-card-list">
        <?php if (!$users): ?>
        <div class="admin-empty">Bu filtrede kullanıcı yok.</div>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
        <article class="mgmt-card<?= empty($u['is_active']) ? ' is-passive' : '' ?>">
            <div class="mgmt-card-main">
                <div class="mgmt-card-title">
                    <strong><?= e($u['name']) ?></strong>
                    <span class="status-pill small <?= !empty($u['is_active']) ? 'status-green' : 'status-slate' ?>"><?= !empty($u['is_active']) ? 'Aktif' : 'Pasif' ?></span>
                </div>
                <div class="mgmt-card-meta">
                    <span>@<?= e($u['username']) ?></span>
                    <?php if (!empty($u['phone'])): ?><span><?= e(format_phone_display((string)$u['phone'])) ?></span><?php endif; ?>
                    <span><?= e($u['group_name'] ?? '—') ?></span>
                </div>
            </div>
            <div class="mgmt-card-actions">
                <a class="btn btn-sm btn-ghost" href="/admin/users.php?status=<?= e($statusFilter) ?>&edit=<?= (int)$u['id'] ?>">Düzenle</a>
                <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm <?= !empty($u['is_active']) ? 'btn-ghost' : 'btn-primary' ?>" type="submit">
                        <?= !empty($u['is_active']) ? 'Pasife al' : 'Aktifleştir' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

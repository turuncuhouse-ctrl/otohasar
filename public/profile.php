<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
$pageTitle = 'Hesabım';
$activeNav = 'profile';
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([(int) $currentUser['id']]);
        $hash = (string) $stmt->fetchColumn();

        if ($current === '' || !password_verify($current, $hash)) {
            $error = 'Mevcut şifre hatalı';
        } elseif (strlen($new) < 4) {
            $error = 'Yeni şifre en az 4 karakter olmalı';
        } elseif ($new !== $confirm) {
            $error = 'Yeni şifreler eşleşmiyor';
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([
                password_hash($new, PASSWORD_BCRYPT),
                (int) $currentUser['id'],
            ]);
            $message = 'Şifreniz güncellendi';
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Hesabım</h1>
</div>

<div class="admin-form-card" style="max-width:420px">
    <p class="dash-sub" style="margin-bottom:1rem">
        <?= e($currentUser['name']) ?> · <?= e(role_labels()[$currentUser['role']] ?? $currentUser['role']) ?>
        · <code><?= e($currentUser['username']) ?></code>
    </p>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <h2>Şifre Değiştir</h2>
        <div class="form-group">
            <label>Mevcut şifre</label>
            <input class="form-input" type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label>Yeni şifre</label>
            <input class="form-input" type="password" name="new_password" required minlength="4" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Yeni şifre (tekrar)</label>
            <input class="form-input" type="password" name="confirm_password" required minlength="4" autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-block" type="submit">Şifreyi Güncelle</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

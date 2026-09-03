<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = authenticate_user();
if ($user) {
    header('Location: ' . user_home_url($user));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $loggedIn = verify_login($username, $password);
    if ($loggedIn) {
        login_user($loggedIn);
        header('Location: ' . user_home_url($loggedIn));
        exit;
    }
    $error = 'Geçersiz kullanıcı adı veya şifre';
}

$pageTitle = 'Giriş';
require __DIR__ . '/../includes/header.php';
?>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <h1>OTOHASAR</h1>
            <p>Hasar Dosya ve Süreç Takip Sistemi</p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" required autocomplete="username" class="form-input">
            </div>
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>
        <p class="portal-login-link"><a href="/musteri/">Müşteri: plaka ile durum sorgula</a></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = authenticate_user();
if ($user) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $loggedIn = verify_login($username, $password);
    if ($loggedIn) {
        login_user($loggedIn);
        header('Location: /dashboard.php');
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
        <div class="demo-chips">
            <p class="demo-label">Demo Hesaplar (şifre: 1234)</p>
            <div class="chip-group">
                <button type="button" class="chip" data-user="admindemo">Admin (sistem ayarları)</button>
                <button type="button" class="chip" data-user="yoneticidemo">Yönetici (dosyalar)</button>
                <button type="button" class="chip" data-user="hasardanismandemo">Danışman — Ahmet</button>
                <button type="button" class="chip" data-user="hasardanisman2demo">Danışman — Burak</button>
                <button type="button" class="chip" data-user="atolyedemo">Atölye — Mehmet</button>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        document.getElementById('username').value = this.dataset.user;
        document.getElementById('password').value = '1234';
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

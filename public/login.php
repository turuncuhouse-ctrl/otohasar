<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = authenticate_user();
if ($user) {
    header('Location: ' . user_home_url($user));
    exit;
}

$error = '';
$loginMode = ($_POST['login_mode'] ?? $_GET['mode'] ?? 'username') === 'phone' ? 'phone' : 'username';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $login = $loginMode === 'phone'
        ? trim($_POST['phone'] ?? '')
        : trim($_POST['username'] ?? '');
    $loggedIn = verify_login($login, $password);
    if ($loggedIn) {
        login_user($loggedIn);
        header('Location: ' . user_home_url($loggedIn));
        exit;
    }
    $error = $loginMode === 'phone'
        ? 'Geçersiz telefon numarası veya şifre'
        : 'Geçersiz kullanıcı adı veya şifre';
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

        <div class="login-mode-toggle" role="tablist" aria-label="Giriş yöntemi">
            <a class="login-mode-btn<?= $loginMode === 'username' ? ' active' : '' ?>" href="/login.php?mode=username">Kullanıcı adı</a>
            <a class="login-mode-btn<?= $loginMode === 'phone' ? ' active' : '' ?>" href="/login.php?mode=phone">Telefon</a>
        </div>

        <form method="post" class="login-form" id="loginForm">
            <input type="hidden" name="login_mode" value="<?= e($loginMode) ?>">
            <?php if ($loginMode === 'phone'): ?>
            <div class="form-group">
                <label for="phone">Telefon</label>
                <div class="phone-login-row">
                    <span class="phone-prefix" aria-hidden="true">+90</span>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        class="form-input phone-login-input"
                        required
                        inputmode="numeric"
                        pattern="[0-9\s]*"
                        autocomplete="tel-national"
                        placeholder="5XX XXX XX XX"
                        maxlength="14"
                    >
                </div>
                <p class="form-hint">Cihazın sayısal klavyesi açılır. Başında 5 olacak şekilde cep numarası girin.</p>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" required autocomplete="username" class="form-input">
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>
        <p class="portal-login-link"><a href="/musteri/">Müşteri: plaka ile durum sorgula</a></p>
    </div>
</div>
<?php
if ($loginMode === 'phone') {
    ob_start();
    ?>
(function(){
    var input = document.getElementById('phone');
    if (!input) return;
    input.addEventListener('input', function(){
        var v = this.value.replace(/\D+/g, '');
        if (v.indexOf('90') === 0) v = v.slice(2);
        if (v.charAt(0) === '0') v = v.slice(1);
        this.value = v.slice(0, 10);
    });
})();
    <?php
    $pageScript = ob_get_clean();
}
require __DIR__ . '/../includes/footer.php';
?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

$error = '';
$prefill = format_plate($_GET['plaka'] ?? '');

function portal_redirect_after_auth(string $plate, ?int $fileId = null, bool $viaToken = false): never
{
    if (!portal_kvkk_accepted()) {
        portal_set_pending_access($plate, $fileId, $viaToken);
        header('Location: /musteri/kvkk.php');
        exit;
    }
    if ($fileId && $fileId > 0) {
        portal_set_file($fileId, $plate, $viaToken);
        header('Location: /musteri/dosya.php?id=' . $fileId);
        exit;
    }
    portal_set_plate($plate);
    $files = find_files_by_plate($plate);
    if (count($files) === 1) {
        portal_set_file((int) $files[0]['id'], $plate, $viaToken);
        header('Location: /musteri/dosya.php?id=' . (int) $files[0]['id']);
        exit;
    }
    header('Location: /musteri/liste.php');
    exit;
}

if (!empty($_GET['t'])) {
    if (portal_rate_limited()) {
        $error = 'Çok fazla deneme. Lütfen bir süre sonra tekrar deneyin.';
    } else {
        $file = find_file_by_customer_token((string) $_GET['t']);
        if ($file) {
            portal_redirect_after_auth((string) $file['plate'], (int) $file['id'], true);
        }
        $error = 'Bağlantı geçersiz veya süresi dolmuş. Plakanızla sorgulayabilirsiniz.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } elseif (portal_rate_limited()) {
        $error = 'Çok fazla deneme. Lütfen bir süre sonra tekrar deneyin.';
    } else {
        $plate = normalize_plate($_POST['plate'] ?? '');
        if ($plate === '' || !is_valid_plate($plate)) {
            $error = 'Geçerli plaka giriniz (ör. 35ABC35)';
        } else {
            $files = find_files_by_plate($plate);
            if (!$files) {
                $error = 'Bu plakaya ait dosya bulunamadı';
            } else {
                $fileId = count($files) === 1 ? (int) $files[0]['id'] : null;
                portal_redirect_after_auth($plate, $fileId, false);
            }
        }
    }
}

$pageTitle = 'Müşteri Sorgulama';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title><?= e($pageTitle) ?> — OTOHASAR</title>
    <link rel="stylesheet" href="<?= e(asset_css_url()) ?>">
</head>
<body class="portal-body">
<main class="portal-wrap">
    <div class="portal-card">
        <div class="portal-brand">
            <h1>OTOHASAR</h1>
            <p>Araç durumu ve eksik evrak</p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
                <label for="plate">Plaka</label>
                <input type="text" id="plate" name="plate" required class="form-input" autocomplete="off"
                       placeholder="35ABC35" value="<?= e($prefill) ?>" style="text-transform:uppercase" maxlength="9">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sorgula</button>
        </form>
        <p class="portal-footnote">Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir. Evrak yükleme yalnızca servisinizin açık izin verdiği süre boyunca mümkündür.</p>
        <a class="portal-staff-link" href="/login.php">Personel girişi</a>
    </div>
</main>
</body>
</html>

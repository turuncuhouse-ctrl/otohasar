<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

$pendingPlate = portal_pending_plate();
$existingPlate = portal_plate();
$plate = $pendingPlate ?: $existingPlate;

if ($plate === null) {
    header('Location: /musteri/');
    exit;
}

if (portal_kvkk_accepted() && $pendingPlate === null) {
    $fileId = portal_file_id();
    if ($fileId) {
        header('Location: /musteri/dosya.php?id=' . $fileId);
        exit;
    }
    header('Location: /musteri/liste.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } elseif (empty($_POST['kvkk_accept'])) {
        $error = 'Devam etmek için KVKK metnini okuyup onaylamanız gerekir.';
    } else {
        start_session();
        $fileId = isset($_SESSION['portal_pending_file_id']) ? (int) $_SESSION['portal_pending_file_id'] : null;
        $viaToken = !empty($_SESSION['portal_pending_via_token']);
        portal_accept_kvkk($plate, $fileId);

        if ($fileId && $fileId > 0) {
            portal_set_file($fileId, $plate, $viaToken);
            unset($_SESSION['portal_pending_plate'], $_SESSION['portal_pending_file_id'], $_SESSION['portal_pending_via_token']);
            header('Location: /musteri/dosya.php?id=' . $fileId);
            exit;
        }

        portal_set_plate($plate);
        unset($_SESSION['portal_pending_plate'], $_SESSION['portal_pending_file_id'], $_SESSION['portal_pending_via_token']);
        $files = find_files_by_plate($plate);
        if (count($files) === 1) {
            portal_set_file((int) $files[0]['id'], $plate, false);
            header('Location: /musteri/dosya.php?id=' . (int) $files[0]['id']);
            exit;
        }
        header('Location: /musteri/liste.php');
        exit;
    }
}

$pageTitle = 'KVKK Onayı';
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
    <div class="portal-card portal-card-wide">
        <div class="portal-brand">
            <h1>KVKK Aydınlatma Metni</h1>
            <p>Plaka: <?= e(format_plate($plate)) ?></p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="kvkk-box">
            <p>6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında; hasar dosyanızın takibi, eksik evrakların alınması ve servis süreçlerinin yürütülmesi amacıyla kimlik, iletişim, araç ve evrak verileriniz OTOHASAR sistemi üzerinden işlenmektedir.</p>
            <p>Verileriniz yalnızca hizmetin sunulması, yasal yükümlülüklerin yerine getirilmesi ve anlaşmalı sigorta/servis süreçleri için gerekli olduğu ölçüde kullanılır. İmzalı taahhüt, teslim ve ibra belgeleriniz dosyanızla ilişkilendirilerek saklanır.</p>
            <p>Bu portala devam ederek kişisel verilerinizin yukarıdaki amaçlarla işlenmesini kabul etmiş olursunuz. Onay vermezseniz sisteme erişemezsiniz.</p>
            <p class="text-muted"><small>Metin sürümü: <?= e(portal_kvkk_version()) ?></small></p>
        </div>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="check-row">
                <input type="checkbox" name="kvkk_accept" value="1" required>
                KVKK aydınlatma metnini okudum ve onaylıyorum.
            </label>
            <button type="submit" class="btn btn-primary btn-block">Onayla ve devam et</button>
        </form>
        <a class="portal-staff-link" href="/musteri/cikis.php">Vazgeç / Çıkış</a>
    </div>
</main>
</body>
</html>

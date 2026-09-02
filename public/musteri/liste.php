<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

$plate = portal_require_plate();
portal_require_kvkk();
$files = find_files_by_plate($plate);
if (!$files) {
    portal_logout();
    header('Location: /musteri/');
    exit;
}
if (count($files) === 1) {
    header('Location: /musteri/dosya.php?id=' . (int) $files[0]['id']);
    exit;
}

$statuses = status_labels();
$pageTitle = 'Dosyalarım';
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
        <div class="portal-top">
            <div>
                <h1>Dosyalarınız</h1>
                <p class="portal-sub"><?= plate_badge_html($plate) ?></p>
            </div>
            <a class="btn btn-ghost btn-sm" href="/musteri/cikis.php">Çıkış</a>
        </div>
        <div class="portal-file-list">
            <?php foreach ($files as $f): ?>
            <a class="portal-file-row" href="/musteri/dosya.php?id=<?= (int)$f['id'] ?>">
                <strong><?= e($f['file_number']) ?></strong>
                <span class="status-pill <?= e(status_colors()[$f['status']] ?? 'status-slate') ?>">
                    <?= e($statuses[$f['status']] ?? $f['status']) ?>
                </span>
                <span class="portal-file-meta"><?= e($f['brand'] . ' ' . $f['model']) ?> · <?= date('d.m.Y', strtotime($f['created_at'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</main>
</body>
</html>

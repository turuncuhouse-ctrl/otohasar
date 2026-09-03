<?php
declare(strict_types=1);
if (!isset($pageTitle)) {
    $pageTitle = 'OTOHASAR';
}
$currentUser = $currentUser ?? null;
$role = $currentUser['role'] ?? '';
$assetVer = asset_version();
$homeUrl = $currentUser ? user_home_url($currentUser) : '/login.php';
$canHasar = $currentUser && user_can($currentUser, 'access_hasar');
$canPrim = $currentUser && user_can($currentUser, 'access_prim');
$canReports = $currentUser && user_can($currentUser, 'access_reports');
$canAdmin = $currentUser && user_can($currentUser, 'access_admin');
$canTour = $currentUser && user_can($currentUser, 'access_tour');
$canCreateFile = $currentUser && user_can($currentUser, 'hasar_create_file');
$canSearch = $currentUser && user_can($currentUser, 'hasar_search');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= e($pageTitle) ?> — OTOHASAR</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <link rel="stylesheet" href="<?= e(asset_css_url()) ?>">
    <?php if ($currentUser): ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php endif; ?>
</head>
<body>
<?php if ($currentUser): ?>
<header class="topbar">
    <div class="topbar-inner">
        <a href="<?= e($homeUrl) ?>" class="logo">OTOHASAR</a>
        <nav class="nav-links" id="mainNav">
            <?php if ($canHasar): ?>
            <a href="/dashboard.php" class="nav-link<?= ($activeNav ?? '') === 'dashboard' ? ' active' : '' ?>">Pano</a>
            <?php endif; ?>
            <?php if ($canCreateFile): ?>
            <a href="/new-file.php" class="nav-link<?= ($activeNav ?? '') === 'new-file' ? ' active' : '' ?>">Yeni Dosya</a>
            <?php endif; ?>
            <?php if ($canSearch): ?>
            <a href="/search.php" class="nav-link<?= ($activeNav ?? '') === 'search' ? ' active' : '' ?>">Ara</a>
            <?php endif; ?>
            <?php if ($canPrim && prim_is_enabled()): ?>
            <a href="/prim/" class="nav-link<?= ($activeNav ?? '') === 'prim' ? ' active' : '' ?>">Prim</a>
            <?php endif; ?>
            <?php if ($canReports): ?>
            <a href="/reports.php" class="nav-link<?= ($activeNav ?? '') === 'reports' ? ' active' : '' ?>">Raporlar</a>
            <?php endif; ?>
            <?php if ($canTour): ?>
            <a href="/tour.php" class="nav-link<?= ($activeNav ?? '') === 'tour' ? ' active' : '' ?>">Tanıtım</a>
            <?php endif; ?>
            <?php if ($canAdmin): ?>
            <a href="/admin/" class="nav-link<?= ($activeNav ?? '') === 'admin' ? ' active' : '' ?>">Sistem Ayarları</a>
            <?php endif; ?>
            <a href="/profile.php" class="nav-link<?= ($activeNav ?? '') === 'profile' ? ' active' : '' ?>">Şifre</a>
            <a href="/logout.php" class="nav-link nav-logout">Çıkış</a>
        </nav>
        <div class="topbar-user">
            <span class="user-badge role-<?= e($role) ?>"><?= e(user_group_label($currentUser)) ?></span>
            <a href="/profile.php" class="user-name"><?= e($currentUser['name']) ?></a>
            <a href="/logout.php" class="btn btn-sm btn-ghost logout-desk">Çıkış</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Menü">☰</button>
    </div>
</header>
<?php endif; ?>
<main class="main-content">

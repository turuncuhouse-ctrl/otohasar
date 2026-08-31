<?php
declare(strict_types=1);
if (!isset($pageTitle)) {
    $pageTitle = 'OTOHASAR';
}
$currentUser = $currentUser ?? null;
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if ($currentUser): ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php endif; ?>
</head>
<body>
<?php if ($currentUser): ?>
<header class="topbar">
    <div class="topbar-inner">
        <a href="/dashboard.php" class="logo">OTOHASAR</a>
        <nav class="nav-links" id="mainNav">
            <a href="/dashboard.php" class="nav-link<?= ($activeNav ?? '') === 'dashboard' ? ' active' : '' ?>">Pano</a>
            <?php if ($currentUser['role'] !== 'workshop'): ?>
            <a href="/new-file.php" class="nav-link<?= ($activeNav ?? '') === 'new-file' ? ' active' : '' ?>">Yeni Dosya</a>
            <?php endif; ?>
            <a href="/search.php" class="nav-link<?= ($activeNav ?? '') === 'search' ? ' active' : '' ?>">Ara</a>
            <?php if ($currentUser['role'] === 'manager' || $currentUser['role'] === 'admin'): ?>
            <a href="/reports.php" class="nav-link<?= ($activeNav ?? '') === 'reports' ? ' active' : '' ?>">Raporlar</a>
            <a href="/admin/" class="nav-link<?= ($activeNav ?? '') === 'admin' ? ' active' : '' ?>">Yönetim</a>
            <?php endif; ?>
            <a href="/logout.php" class="nav-link nav-logout">Çıkış</a>
        </nav>
        <div class="topbar-user">
            <span class="user-badge role-<?= e($currentUser['role']) ?>"><?= e(role_labels()[$currentUser['role']] ?? $currentUser['role']) ?></span>
            <span class="user-name"><?= e($currentUser['name']) ?></span>
            <a href="/logout.php" class="btn btn-sm btn-ghost logout-desk">Çıkış</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Menü">☰</button>
    </div>
</header>
<?php endif; ?>
<main class="main-content">

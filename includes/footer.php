</main>
<?php if ($currentUser):
    $role = $currentUser['role'] ?? '';
?>
<nav class="bottom-nav" aria-label="Mobil menü">
    <a href="/dashboard.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
        <span>Pano</span>
    </a>
    <?php if (!in_array($role, ['workshop', 'admin'], true)): ?>
    <a href="/new-file.php" class="<?= ($activeNav ?? '') === 'new-file' ? 'active' : '' ?>">
        <span>Yeni</span>
    </a>
    <?php endif; ?>
    <a href="/search.php" class="<?= ($activeNav ?? '') === 'search' ? 'active' : '' ?>">
        <span>Ara</span>
    </a>
    <?php if ($role === 'manager'): ?>
    <a href="/reports.php" class="<?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">
        <span>Rapor</span>
    </a>
    <?php endif; ?>
    <?php if ($role === 'admin'): ?>
    <a href="/admin/" class="<?= ($activeNav ?? '') === 'admin' ? 'active' : '' ?>">
        <span>Ayarlar</span>
    </a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<div id="toastContainer" class="toast-container"></div>
<?php $assetVer = asset_version(); ?>
<script src="<?= e(asset_js_url()) ?>"></script>
<?php if (isset($pageScript)): ?>
<script><?= $pageScript ?></script>
<?php endif; ?>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js?v=<?= e($assetVer) ?>').catch(function(){});
}
</script>
</body>
</html>

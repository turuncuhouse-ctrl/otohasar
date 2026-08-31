</main>
<?php if ($currentUser): ?>
<nav class="bottom-nav" aria-label="Mobil menü">
    <a href="/dashboard.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
        <span>Pano</span>
    </a>
    <?php if ($currentUser['role'] !== 'workshop'): ?>
    <a href="/new-file.php" class="<?= ($activeNav ?? '') === 'new-file' ? 'active' : '' ?>">
        <span>Yeni</span>
    </a>
    <?php endif; ?>
    <a href="/search.php" class="<?= ($activeNav ?? '') === 'search' ? 'active' : '' ?>">
        <span>Ara</span>
    </a>
    <?php if ($currentUser['role'] === 'manager'): ?>
    <a href="/reports.php" class="<?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">
        <span>Rapor</span>
    </a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<div id="toastContainer" class="toast-container"></div>
<script src="/assets/js/app.js"></script>
<?php if (isset($pageScript)): ?>
<script><?= $pageScript ?></script>
<?php endif; ?>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>

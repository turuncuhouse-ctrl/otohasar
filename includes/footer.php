</main>
<?php if ($currentUser):
    $canHasar = user_can($currentUser, 'access_hasar');
    $canPrim = user_can($currentUser, 'access_prim') && prim_is_enabled();
    $canReports = user_can($currentUser, 'access_reports');
    $canAdmin = user_can($currentUser, 'access_admin');
    $canTour = user_can($currentUser, 'access_tour');
    $canCreateFile = user_can($currentUser, 'hasar_create_file');
    $canSearch = user_can($currentUser, 'hasar_search');
?>
<nav class="bottom-nav" aria-label="Mobil menü">
    <?php if ($canHasar): ?>
    <a href="/dashboard.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
        <span>Pano</span>
    </a>
    <?php endif; ?>
    <?php if ($canCreateFile): ?>
    <a href="/new-file.php" class="<?= ($activeNav ?? '') === 'new-file' ? 'active' : '' ?>">
        <span>Yeni</span>
    </a>
    <?php endif; ?>
    <?php if ($canSearch): ?>
    <a href="/search.php" class="<?= ($activeNav ?? '') === 'search' ? 'active' : '' ?>">
        <span>Ara</span>
    </a>
    <?php endif; ?>
    <?php if ($canPrim): ?>
    <a href="/prim/" class="<?= ($activeNav ?? '') === 'prim' ? 'active' : '' ?>">
        <span>Prim</span>
    </a>
    <?php endif; ?>
    <?php if ($canReports): ?>
    <a href="/reports.php" class="<?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">
        <span>Rapor</span>
    </a>
    <?php endif; ?>
    <?php if ($canTour): ?>
    <a href="/tour.php" class="<?= ($activeNav ?? '') === 'tour' ? 'active' : '' ?>">
        <span>Tanıtım</span>
    </a>
    <?php endif; ?>
    <?php if ($canAdmin): ?>
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
    var swUrl = '/sw.js?v=<?= e($assetVer) ?>';
    navigator.serviceWorker.getRegistrations().then(function(regs) {
        return Promise.all(regs.map(function(reg) {
            if (reg.active && reg.active.scriptURL && reg.active.scriptURL.indexOf('sw.js') !== -1) {
                return reg.update();
            }
            return null;
        }));
    }).finally(function() {
        navigator.serviceWorker.register(swUrl).then(function(reg) {
            if (reg && reg.update) reg.update();
        }).catch(function(){});
    });
}
</script>
</body>
</html>

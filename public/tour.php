<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
require_perm($currentUser, 'access_tour');

$pageTitle = 'Tanıtım';
$activeNav = 'tour';
$slides = tour_slides(true);
$total = count($slides);

if (empty($currentUser['tour_seen_at'])) {
    try {
        db()->prepare('UPDATE users SET tour_seen_at = NOW() WHERE id = ?')->execute([(int) $currentUser['id']]);
    } catch (Throwable $e) {
    }
}

require __DIR__ . '/../includes/header.php';
?>

<?php if (!$slides): ?>
<div class="page-header">
    <h1>Sistem Tanıtımı</h1>
    <a href="<?= e(user_home_url($currentUser)) ?>" class="btn btn-ghost btn-sm">Ana sayfa</a>
</div>
<div class="alert alert-error">Tanıtım slaytı henüz yok. Sistem Ayarları → Tanıtım Sunumu.</div>
<?php else: ?>
<div class="tour-stage" id="tourDeck" data-count="<?= $total ?>">
    <aside class="tour-rail" aria-label="Bölümler">
        <div class="tour-brand">
            <span class="tour-brand-mark">OTOHASAR</span>
            <span class="tour-brand-sub">Sistem tanıtımı</span>
        </div>
        <ol class="tour-toc">
            <?php foreach ($slides as $i => $slide): ?>
            <li>
                <button type="button" class="tour-toc-item<?= $i === 0 ? ' active' : '' ?>" data-go="<?= $i ?>">
                    <span class="tour-toc-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="tour-toc-text">
                        <span class="tour-toc-eye"><?= e($slide['eyebrow'] ?: 'Bölüm') ?></span>
                        <span class="tour-toc-title"><?= e($slide['title']) ?></span>
                    </span>
                </button>
            </li>
            <?php endforeach; ?>
        </ol>
    </aside>

    <section class="tour-main">
        <div class="tour-progress" aria-hidden="true"><i id="tourProgress" style="width:<?= $total ? round(100 / $total, 2) : 0 ?>%"></i></div>

        <?php foreach ($slides as $i => $slide):
            $bullets = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($slide['bullets'] ?? '')) ?: [])));
        ?>
        <article class="tour-slide<?= $i === 0 ? ' is-active' : '' ?>" data-index="<?= $i ?>">
            <header class="tour-slide-head">
                <p class="tour-eyebrow"><?= e($slide['eyebrow'] ?: 'OTOHASAR') ?></p>
                <p class="tour-kicker">Bölüm <?= $i + 1 ?> / <?= $total ?></p>
            </header>
            <h1 class="tour-headline"><?= e($slide['title']) ?></h1>
            <div class="tour-copy"><?= nl2br(e($slide['body'])) ?></div>
            <?php if ($bullets): ?>
            <ul class="tour-points">
                <?php foreach ($bullets as $b): ?>
                <li><?= e($b) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>

        <footer class="tour-footer">
            <button type="button" class="btn btn-ghost" id="tourPrev" disabled>Önceki</button>
            <div class="tour-dots" id="tourDots">
                <?php foreach ($slides as $i => $_s): ?>
                <button type="button" class="tour-dot<?= $i === 0 ? ' active' : '' ?>" data-go="<?= $i ?>" aria-label="Slayt <?= $i + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-primary" id="tourNext">Devam</button>
        </footer>
    </section>
</div>
<?php
ob_start();
?>
(function(){
    var deck = document.getElementById('tourDeck');
    if (!deck) return;
    var slides = deck.querySelectorAll('.tour-slide');
    var dots = deck.querySelectorAll('.tour-dot');
    var toc = deck.querySelectorAll('.tour-toc-item');
    var prev = document.getElementById('tourPrev');
    var next = document.getElementById('tourNext');
    var bar = document.getElementById('tourProgress');
    var i = 0;
    function show(n) {
        i = Math.max(0, Math.min(slides.length - 1, n));
        slides.forEach(function(s, idx){ s.classList.toggle('is-active', idx === i); });
        dots.forEach(function(d, idx){ d.classList.toggle('active', idx === i); });
        toc.forEach(function(d, idx){ d.classList.toggle('active', idx === i); });
        prev.disabled = i === 0;
        next.textContent = i === slides.length - 1 ? 'Kapat' : 'Devam';
        if (bar) bar.style.width = (((i + 1) / slides.length) * 100) + '%';
    }
    prev.addEventListener('click', function(){ show(i - 1); });
    next.addEventListener('click', function(){
        if (i >= slides.length - 1) {
            window.location.href = <?= json_encode(user_home_url($currentUser)) ?>;
            return;
        }
        show(i + 1);
    });
    function bindGo(nodes) {
        nodes.forEach(function(d){
            d.addEventListener('click', function(){ show(parseInt(this.dataset.go, 10) || 0); });
        });
    }
    bindGo(dots);
    bindGo(toc);
    document.addEventListener('keydown', function(e){
        if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); next.click(); }
        if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); prev.click(); }
    });
})();
<?php
$pageScript = ob_get_clean();
endif;

require __DIR__ . '/../includes/footer.php';
?>

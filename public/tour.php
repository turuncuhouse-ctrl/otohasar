<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
require_perm($currentUser, 'access_tour');

$pageTitle = 'Tanıtım';
$activeNav = 'tour';
$slides = tour_slides(true);

if (empty($currentUser['tour_seen_at'])) {
    try {
        db()->prepare('UPDATE users SET tour_seen_at = NOW() WHERE id = ?')->execute([(int) $currentUser['id']]);
    } catch (Throwable $e) {
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Sistem Tanıtımı</h1>
    <a href="<?= e(user_home_url($currentUser)) ?>" class="btn btn-ghost btn-sm">Ana sayfa</a>
</div>

<?php if (!$slides): ?>
<div class="alert alert-error">Tanıtım slaytı henüz tanımlanmamış. Sistem Admin → Tanıtım Sunumu.</div>
<?php else: ?>
<div class="tour-deck" id="tourDeck" data-count="<?= count($slides) ?>">
    <?php foreach ($slides as $i => $slide): ?>
    <article class="tour-slide<?= $i === 0 ? ' is-active' : '' ?>" data-index="<?= $i ?>">
        <p class="tour-step">Adım <?= $i + 1 ?> / <?= count($slides) ?></p>
        <h2><?= e($slide['title']) ?></h2>
        <div class="tour-body"><?= nl2br(e($slide['body'])) ?></div>
    </article>
    <?php endforeach; ?>
    <div class="tour-nav">
        <button type="button" class="btn btn-ghost" id="tourPrev" disabled>← Önceki</button>
        <div class="tour-dots" id="tourDots">
            <?php foreach ($slides as $i => $_s): ?>
            <button type="button" class="tour-dot<?= $i === 0 ? ' active' : '' ?>" data-go="<?= $i ?>" aria-label="Slayt <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary" id="tourNext">Sonraki →</button>
    </div>
</div>
<?php
ob_start();
?>
(function(){
    var deck = document.getElementById('tourDeck');
    if (!deck) return;
    var slides = deck.querySelectorAll('.tour-slide');
    var dots = deck.querySelectorAll('.tour-dot');
    var prev = document.getElementById('tourPrev');
    var next = document.getElementById('tourNext');
    var i = 0;
    function show(n) {
        i = Math.max(0, Math.min(slides.length - 1, n));
        slides.forEach(function(s, idx){ s.classList.toggle('is-active', idx === i); });
        dots.forEach(function(d, idx){ d.classList.toggle('active', idx === i); });
        prev.disabled = i === 0;
        next.textContent = i === slides.length - 1 ? 'Bitir' : 'Sonraki →';
    }
    prev.addEventListener('click', function(){ show(i - 1); });
    next.addEventListener('click', function(){
        if (i >= slides.length - 1) {
            window.location.href = <?= json_encode(user_home_url($currentUser)) ?>;
            return;
        }
        show(i + 1);
    });
    dots.forEach(function(d){
        d.addEventListener('click', function(){ show(parseInt(this.dataset.go, 10) || 0); });
    });
})();
<?php
$pageScript = ob_get_clean();
endif;

require __DIR__ . '/../includes/footer.php';
?>

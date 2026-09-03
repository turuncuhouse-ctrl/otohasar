<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
require_perm($currentUser, 'access_prim');
if (!prim_is_enabled()) {
    http_response_code(403);
    exit('Prim sistemi kapalı');
}

$pageTitle = 'Prim';
$activeNav = 'prim';
$pdo = db();
$canTeam = user_can($currentUser, 'prim_view_team');
$canAmounts = user_can($currentUser, 'prim_view_amounts');
$canCreate = user_can($currentUser, 'prim_sale_create');
$windowDays = (int) prim_setting('prim_window_days', '30');

$from = date('Y-m-d', strtotime('-' . max(1, $windowDays) . ' days'));
$to = date('Y-m-d');

$ownSql = 'SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(amount),0) AS total
           FROM prim_sales WHERE sold_by = ? AND sale_at >= ? AND sale_at < DATE_ADD(?, INTERVAL 1 DAY)';
$stmt = $pdo->prepare($ownSql);
$stmt->execute([(int) $currentUser['id'], $from . ' 00:00:00', $to]);
$own = $stmt->fetch() ?: ['cnt' => 0, 'qty' => 0, 'total' => 0];
$ownPrim = 0.0;
if ($canAmounts) {
    $stmt = $pdo->prepare(
        'SELECT amount, quantity FROM prim_sales WHERE sold_by = ? AND sale_at >= ? AND sale_at < DATE_ADD(?, INTERVAL 1 DAY)'
    );
    $stmt->execute([(int) $currentUser['id'], $from . ' 00:00:00', $to]);
    foreach ($stmt->fetchAll() as $row) {
        $ownPrim += prim_calc_amount((float) $row['amount'], (int) $row['quantity']);
    }
}

$team = [];
if ($canTeam) {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name,
                COUNT(ps.id) AS sale_count,
                COALESCE(SUM(ps.quantity),0) AS qty,
                COALESCE(SUM(ps.amount),0) AS total
         FROM users u
         INNER JOIN prim_sales ps ON ps.sold_by = u.id
         WHERE ps.sale_at >= ? AND ps.sale_at < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY u.id
         ORDER BY total DESC, qty DESC"
    );
    $stmt->execute([$from . ' 00:00:00', $to]);
    $team = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Prim Panosu</h1>
        <p class="dash-sub">Son <?= (int)$windowDays ?> gün · <?= e($from) ?> — <?= e($to) ?></p>
    </div>
    <div class="header-actions">
        <?php if ($canCreate): ?>
        <a href="/prim/sale.php" class="btn btn-primary">+ Satış Kaydet</a>
        <?php endif; ?>
        <a href="/prim/sales.php" class="btn btn-ghost">Satış Listesi</a>
    </div>
</div>

<div class="stat-grid prim-stats">
    <div class="stat-card stat-total">
        <span class="stat-num"><?= (int)$own['qty'] ?></span>
        <span class="stat-label">Satılan adetim</span>
    </div>
    <div class="stat-card status-blue">
        <span class="stat-num"><?= (int)$own['cnt'] ?></span>
        <span class="stat-label">Satış kaydım</span>
    </div>
    <?php if ($canAmounts): ?>
    <div class="stat-card status-green">
        <span class="stat-num"><?= e(number_format((float)$own['total'], 0, ',', '.')) ?></span>
        <span class="stat-label">Satış tutarım (TL)</span>
    </div>
    <div class="stat-card status-amber">
        <span class="stat-num"><?= e(number_format($ownPrim, 0, ',', '.')) ?></span>
        <span class="stat-label">Hak ettiğim prim (TL)</span>
    </div>
    <?php endif; ?>
</div>

<?php if ($canTeam): ?>
<div class="admin-table-wrap" style="margin-top:1.25rem">
    <h2 class="section-title">Ekip özeti</h2>
    <table class="report-table">
        <thead>
            <tr>
                <th>Kullanıcı</th>
                <th>Adet</th>
                <th>Kayıt</th>
                <?php if ($canAmounts): ?><th>Satış (TL)</th><th>Prim (TL)</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!$team): ?>
            <tr><td colspan="5">Bu dönemde satış yok.</td></tr>
        <?php else: ?>
            <?php foreach ($team as $row):
                $primTotal = 0.0;
                if ($canAmounts) {
                    $s = $pdo->prepare(
                        'SELECT amount, quantity FROM prim_sales WHERE sold_by = ? AND sale_at >= ? AND sale_at < DATE_ADD(?, INTERVAL 1 DAY)'
                    );
                    $s->execute([(int)$row['id'], $from . ' 00:00:00', $to]);
                    foreach ($s->fetchAll() as $sale) {
                        $primTotal += prim_calc_amount((float)$sale['amount'], (int)$sale['quantity']);
                    }
                }
            ?>
            <tr>
                <td><?= e($row['name']) ?></td>
                <td><?= (int)$row['qty'] ?></td>
                <td><?= (int)$row['sale_count'] ?></td>
                <?php if ($canAmounts): ?>
                <td><?= e(format_money_tr((float)$row['total'])) ?></td>
                <td><?= e(format_money_tr($primTotal)) ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

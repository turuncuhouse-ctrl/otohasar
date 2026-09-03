<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
require_perm($currentUser, 'access_prim');
if (!prim_is_enabled()) {
    http_response_code(403);
    exit('Prim sistemi kapalı');
}
if (!user_can($currentUser, 'prim_view_own') && !user_can($currentUser, 'prim_view_team')) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Satış Listesi';
$activeNav = 'prim';
$pdo = db();
$canTeam = user_can($currentUser, 'prim_view_team');
$canAmounts = user_can($currentUser, 'prim_view_amounts');
$canEditOwn = user_can($currentUser, 'prim_sale_edit_own');
$message = isset($_GET['ok']) ? 'Kayıt güncellendi' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $message = '';
    } else {
        $delId = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM prim_sales WHERE id = ?');
        $stmt->execute([$delId]);
        $row = $stmt->fetch();
        if ($row && (int) $row['sold_by'] === (int) $currentUser['id'] && $canEditOwn) {
            $pdo->prepare('DELETE FROM prim_sales WHERE id = ?')->execute([$delId]);
            $message = 'Satış silindi';
        }
    }
}

$params = [];
$sql = 'SELECT ps.*, u.name AS seller_name
        FROM prim_sales ps
        JOIN users u ON u.id = ps.sold_by';
if (!$canTeam) {
    $sql .= ' WHERE ps.sold_by = ?';
    $params[] = (int) $currentUser['id'];
}
$sql .= ' ORDER BY ps.sale_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$ctxLabels = ['kabul' => 'Kabul', 'teslim' => 'Teslim', 'diger' => 'Diğer'];

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Satış Listesi</h1>
    <div class="header-actions">
        <?php if (user_can($currentUser, 'prim_sale_create')): ?>
        <a href="/prim/sale.php" class="btn btn-primary btn-sm">+ Satış</a>
        <?php endif; ?>
        <a href="/prim/" class="btn btn-ghost btn-sm">← Prim</a>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

<div class="admin-table-wrap">
    <table class="report-table">
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Başlık</th>
                <th>Plaka</th>
                <th>Bağlam</th>
                <th>Adet</th>
                <?php if ($canAmounts): ?><th>Tutar</th><th>Prim</th><?php endif; ?>
                <?php if ($canTeam): ?><th>Satan</th><?php endif; ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$sales): ?>
            <tr><td colspan="9">Kayıt yok.</td></tr>
        <?php else: ?>
            <?php foreach ($sales as $s):
                $isOwn = (int)$s['sold_by'] === (int)$currentUser['id'];
            ?>
            <tr>
                <td><?= e(format_datetime_short($s['sale_at'])) ?></td>
                <td><?= e($s['title']) ?></td>
                <td><?= e($s['plate'] ?: '—') ?></td>
                <td><?= e($ctxLabels[$s['context']] ?? $s['context']) ?></td>
                <td><?= (int)$s['quantity'] ?></td>
                <?php if ($canAmounts): ?>
                <td><?= e(format_money_tr((float)$s['amount'])) ?></td>
                <td><?= e(format_money_tr(prim_calc_amount((float)$s['amount'], (int)$s['quantity']))) ?></td>
                <?php endif; ?>
                <?php if ($canTeam): ?><td><?= e($s['seller_name']) ?></td><?php endif; ?>
                <td class="table-actions">
                    <?php if ($isOwn && $canEditOwn): ?>
                    <a class="btn btn-sm btn-ghost" href="/prim/sale.php?id=<?= (int)$s['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Sil</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

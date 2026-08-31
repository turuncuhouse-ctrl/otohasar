<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
$pageTitle = 'Arama';
$activeNav = 'search';

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $pdo = db();
    $like = '%' . $q . '%';
    $plateLike = '%' . str_replace(' ', '%', strtoupper($q)) . '%';

    $sql = "SELECT df.id, df.file_number, df.status, df.insurance_company,
                   v.plate, v.brand, v.model,
                   c.name AS customer_name, c.phone AS customer_phone,
                   u.name AS advisor_name
            FROM damage_files df
            JOIN vehicles v ON v.id = df.vehicle_id
            JOIN customers c ON c.id = v.customer_id
            JOIN users u ON u.id = df.advisor_id
            WHERE (df.file_number LIKE ? OR v.plate LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR df.claim_no LIKE ?)
            ORDER BY df.updated_at DESC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $plateLike, $like, $like, $like]);
    $results = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Dosya Arama</h1>
</div>

<form method="get" class="search-form">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Plaka, dosya no, müşteri veya telefon..." class="form-input search-input" autofocus>
    <button type="submit" class="btn btn-primary">Ara</button>
</form>

<?php if ($q !== ''): ?>
<p class="search-count"><?= count($results) ?> sonuç bulundu</p>
<div class="search-results">
    <?php foreach ($results as $r): ?>
    <a href="/file.php?id=<?= (int)$r['id'] ?>" class="search-result-card">
        <div class="result-top">
            <?= plate_badge_html($r['plate']) ?>
            <span class="result-file-no"><?= e($r['file_number']) ?></span>
            <span class="status-pill small <?= e(status_colors()[$r['status']]) ?>"><?= e(status_labels()[$r['status']]) ?></span>
        </div>
        <div class="result-body">
            <span><?= e($r['brand'] . ' ' . $r['model']) ?></span>
            <span><?= e($r['customer_name']) ?> · <?= e($r['customer_phone'] ?? '') ?></span>
            <span><?= e($r['insurance_company'] ?? '') ?> · <?= e($r['advisor_name']) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($results)): ?>
    <p class="empty-state">Sonuç bulunamadı.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
$pageTitle = 'Arama';
$activeNav = 'search';

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$insurance = trim($_GET['insurance'] ?? '');
$results = [];
$searched = isset($_GET['q']) || isset($_GET['status']) || isset($_GET['insurance']);

$pdo = db();
$insurers = insurance_companies(true);
$statuses = status_labels();
$colors = status_colors();

if ($searched) {
    $where = ['1=1'];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $plateLike = '%' . str_replace(' ', '%', strtoupper($q)) . '%';
        $where[] = '(df.file_number LIKE ? OR v.plate LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR df.claim_no LIKE ? OR df.policy_no LIKE ?)';
        array_push($params, $like, $plateLike, $like, $like, $like, $like);
    }
    if ($status !== '' && isset($statuses[$status])) {
        $where[] = 'df.status = ?';
        $params[] = $status;
    }
    if ($insurance !== '') {
        $where[] = 'df.insurance_company = ?';
        $params[] = $insurance;
    }

    $sql = 'SELECT df.id, df.file_number, df.status, df.insurance_company, df.created_at, df.status_changed_at, df.updated_at,
                   v.plate, v.brand, v.model,
                   c.name AS customer_name, c.phone AS customer_phone,
                   u.name AS advisor_name,
                   (SELECT COUNT(*) FROM file_documents fd WHERE fd.damage_file_id = df.id) AS doc_count
            FROM damage_files df
            JOIN vehicles v ON v.id = df.vehicle_id
            JOIN customers c ON c.id = v.customer_id
            JOIN users u ON u.id = df.advisor_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY df.updated_at DESC
            LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Dosya Arama</h1>
</div>

<form method="get" class="search-panel">
    <div class="form-group">
        <label>Anahtar kelime</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Plaka, dosya no, müşteri, telefon, poliçe..." class="form-input search-input" autofocus>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Durum</label>
            <select name="status" class="form-input">
                <option value="">Tümü</option>
                <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Sigorta</label>
            <select name="insurance" class="form-input">
                <option value="">Tümü</option>
                <?php foreach ($insurers as $ins): ?>
                <option value="<?= e($ins['name']) ?>" <?= $insurance === $ins['name'] ? 'selected' : '' ?>><?= e($ins['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Ara</button>
</form>

<?php if ($searched): ?>
<p class="search-count"><?= count($results) ?> sonuç</p>
<div class="search-results">
    <?php foreach ($results as $r): ?>
    <div class="search-result-card search-result-row">
        <a href="/file.php?id=<?= (int)$r['id'] ?>" class="search-result-link">
            <div class="result-top">
                <?= plate_badge_html($r['plate']) ?>
                <span class="result-file-no"><?= e($r['file_number']) ?></span>
                <span class="status-pill small <?= e($colors[$r['status']] ?? 'status-slate') ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
            </div>
            <div class="result-body">
                <span><?= e($r['brand'] . ' ' . $r['model']) ?> · 📎 <?= (int)$r['doc_count'] ?> evrak</span>
                <span><?= e($r['customer_name']) ?> · <?= e($r['customer_phone'] ?? '') ?></span>
                <span><?= e($r['insurance_company'] ?? '-') ?> · <?= e($r['advisor_name']) ?></span>
                <span class="result-dates">Açılış <?= e(format_datetime_short($r['created_at'] ?? null)) ?> · Durum <?= e(format_datetime_short($r['status_changed_at'] ?? $r['created_at'] ?? null)) ?></span>
            </div>
        </a>
        <div class="search-result-actions">
            <?php if ((int)$r['doc_count'] > 0): ?>
            <a class="btn btn-sm btn-primary" href="/api/download_zip.php?file_id=<?= (int)$r['id'] ?>">ZIP İndir</a>
            <a class="btn btn-sm btn-ghost" href="/api/download_zip.php?plate=<?= urlencode($r['plate']) ?>">Plaka ZIP</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="/file.php?id=<?= (int)$r['id'] ?>">Aç</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($results)): ?>
    <p class="empty-state">Sonuç bulunamadı.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

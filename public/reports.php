<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
require_role($currentUser, ['manager']);

$pageTitle = 'Raporlar';
$activeNav = 'reports';

$pdo = db();

$stmt = $pdo->query(
    "SELECT u.name, u.id,
            COUNT(df.id) AS total_files,
            SUM(CASE WHEN df.status != 'tamamlandi' THEN 1 ELSE 0 END) AS active_files
     FROM users u
     LEFT JOIN damage_files df ON df.advisor_id = u.id
     WHERE u.role = 'advisor' AND u.is_active = 1
     GROUP BY u.id
     ORDER BY active_files DESC"
);
$advisorStats = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM damage_files GROUP BY status"
);
$statusCounts = $stmt->fetchAll();
$statusMap = [];
$totalFiles = 0;
foreach ($statusCounts as $sc) {
    $statusMap[$sc['status']] = (int) $sc['cnt'];
    $totalFiles += (int) $sc['cnt'];
}

$stmt = $pdo->query(
    "SELECT fl.*, u.name AS user_name, df.file_number, v.plate
     FROM file_logs fl
     JOIN users u ON u.id = fl.user_id
     JOIN damage_files df ON df.id = fl.damage_file_id
     JOIN vehicles v ON v.id = df.vehicle_id
     ORDER BY fl.created_at DESC
     LIMIT 20"
);
$recentLogs = $stmt->fetchAll();

$statuses = status_labels();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Raporlar</h1>
</div>

<div class="reports-grid">
    <div class="report-card">
        <h2>Danışman İş Yükü</h2>
        <table class="report-table">
            <thead><tr><th>Danışman</th><th>Aktif</th><th>Toplam</th></tr></thead>
            <tbody>
            <?php foreach ($advisorStats as $as): ?>
            <tr>
                <td><?= e($as['name']) ?></td>
                <td><strong><?= (int)$as['active_files'] ?></strong></td>
                <td><?= (int)$as['total_files'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="report-card">
        <h2>Durum Dağılımı</h2>
        <div class="bar-chart">
            <?php foreach ($statuses as $key => $label):
                $cnt = $statusMap[$key] ?? 0;
                $pct = $totalFiles > 0 ? round($cnt / $totalFiles * 100) : 0;
            ?>
            <div class="bar-row">
                <span class="bar-label"><?= e($label) ?></span>
                <div class="bar-track">
                    <div class="bar-fill <?= e(status_colors()[$key]) ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="bar-count"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="report-card full-width">
        <h2>Son Hareketler</h2>
        <div class="timeline compact">
            <?php foreach ($recentLogs as $log): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <p>
                        <a href="/file.php?id=<?= (int)$log['damage_file_id'] ?>"><?= e($log['file_number']) ?></a>
                        (<?= e($log['plate']) ?>) — <?= e($log['action_description']) ?>
                    </p>
                    <span class="timeline-meta"><?= e($log['user_name']) ?> · <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

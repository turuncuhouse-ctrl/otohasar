<?php
/**
 * v22: Demo duyuru seed (v21 tablosu varsa ve boşsa)
 */
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']),
    $db['user'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function v22_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!v22_table_exists($pdo, 'app_announcements')) {
    echo "migrate_v22: app_announcements missing, skip\n";
    exit(0);
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM app_announcements')->fetchColumn();
if ($count === 0) {
    $pdo->prepare(
        'INSERT INTO app_announcements (body, link_url, starts_at, ends_at, sort_order, is_active)
         VALUES (?, NULL, NULL, NULL, 0, 1)'
    )->execute(['OTOHASAR demo duyuru — panelli kayan yazı örneği']);
    echo "migrate_v22: demo announcement seeded\n";
} else {
    echo "migrate_v22: announcements already present, skip seed\n";
}

if (v22_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v22_announcement_demo']);
}

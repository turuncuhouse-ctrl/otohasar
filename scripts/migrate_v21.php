<?php
/**
 * v21: Sistem duyuruları (kayan yazı, süreli)
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

function v21_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_announcements (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        body VARCHAR(500) NOT NULL,
        link_url VARCHAR(255) NULL,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (v21_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v21_announcements']);
}

echo "migrate_v21: announcements OK\n";

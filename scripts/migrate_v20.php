<?php
/**
 * v20: Prim hedeflerine ürün bağlama (bireysel ürün hedefleri)
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

function v20_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!v20_table_exists($pdo, 'prim_targets')) {
    echo "migrate_v20: prim_targets missing\n";
    exit(0);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS prim_target_products (
        target_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (target_id, product_id),
        CONSTRAINT fk_ptp_target FOREIGN KEY (target_id) REFERENCES prim_targets(id) ON DELETE CASCADE,
        CONSTRAINT fk_ptp_product FOREIGN KEY (product_id) REFERENCES prim_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (v20_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v20_prim_target_products']);
}

echo "migrate_v20: prim_target_products OK\n";

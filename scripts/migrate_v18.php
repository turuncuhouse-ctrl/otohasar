<?php
/**
 * v18: Esnek prim — ürün katalogu, bireysel/ekip hedefler, kademeler
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

function v18_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function v18_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS prim_products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) NULL,
        name VARCHAR(160) NOT NULL,
        category VARCHAR(80) NULL,
        commission_mode ENUM('pct','fixed','inherit') NOT NULL DEFAULT 'pct',
        rate_pct DECIMAL(8,2) NOT NULL DEFAULT 0,
        fixed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        spiff_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_prim_products_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS prim_targets (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        scope ENUM('user','team') NOT NULL DEFAULT 'user',
        user_id INT UNSIGNED NULL,
        team_label VARCHAR(120) NULL,
        period_type ENUM('month','custom') NOT NULL DEFAULT 'month',
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        metric ENUM('amount','quantity','sales_count') NOT NULL DEFAULT 'amount',
        target_value DECIMAL(14,2) NOT NULL DEFAULT 0,
        bonus_mode ENUM('fixed','pct_of_sales','none') NOT NULL DEFAULT 'fixed',
        bonus_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_prim_targets_user (user_id),
        KEY idx_prim_targets_period (period_start, period_end)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS prim_target_tiers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        target_id INT UNSIGNED NOT NULL,
        min_pct DECIMAL(8,2) NOT NULL DEFAULT 100,
        bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        label VARCHAR(80) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_prim_tiers_target FOREIGN KEY (target_id) REFERENCES prim_targets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (v18_table_exists($pdo, 'prim_sales')) {
    if (!v18_column_exists($pdo, 'prim_sales', 'product_id')) {
        $pdo->exec('ALTER TABLE prim_sales ADD COLUMN product_id INT UNSIGNED NULL AFTER damage_file_id');
    }
    if (!v18_column_exists($pdo, 'prim_sales', 'earned_prim')) {
        $pdo->exec('ALTER TABLE prim_sales ADD COLUMN earned_prim DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount');
    }
}

if (!v18_table_exists($pdo, 'app_settings')) {
    $pdo->exec(
        "CREATE TABLE app_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$set = $pdo->prepare(
    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_key = setting_key'
);
foreach ([
    'prim_calc_priority' => 'product_then_global', // product_then_global | global_only | product_only
    'prim_include_spiff' => '1',
    'prim_stack_target_bonus' => '1',
] as $k => $v) {
    $set->execute([$k, $v]);
}

$cnt = (int) $pdo->query('SELECT COUNT(*) FROM prim_products')->fetchColumn();
if ($cnt === 0) {
    $ins = $pdo->prepare(
        'INSERT INTO prim_products (code, name, category, commission_mode, rate_pct, fixed_amount, spiff_amount, sort_order, is_active)
         VALUES (?,?,?,?,?,?,?,?,1)'
    );
    $seed = [
        ['CAM_FILM', 'Cam Filmi', 'Aksesuar', 'pct', 10, 0, 0, 10],
        ['SERAMIK', 'Seramik Kaplama', 'Detay', 'pct', 8, 0, 250, 20],
        ['BAKIM_PKT', 'Periyodik Bakım Paketi', 'Servis', 'pct', 5, 0, 0, 30],
        ['LASTIK', 'Lastik Satışı', 'Lastik', 'fixed', 0, 75, 0, 40],
        ['YIKAMA', 'Özel Yıkama / Detailing', 'Detay', 'fixed', 0, 50, 0, 50],
        ['DIGER', 'Diğer Ek Satış', 'Genel', 'inherit', 0, 0, 0, 90],
    ];
    foreach ($seed as $row) {
        $ins->execute($row);
    }
}

if (v18_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v18_prim_flexible']);
}

echo "migrate_v18: flexible prim OK\n";

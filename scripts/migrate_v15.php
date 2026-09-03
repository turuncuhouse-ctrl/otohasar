<?php
/**
 * v15: Esnek kullanıcı grupları + izin matrisi
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

function v15_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function v15_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_groups (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) NOT NULL,
        name VARCHAR(120) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_groups_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS group_permissions (
        group_id INT UNSIGNED NOT NULL,
        perm_key VARCHAR(60) NOT NULL,
        allowed TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (group_id, perm_key),
        CONSTRAINT fk_group_permissions_group
            FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (!v15_column_exists($pdo, 'users', 'group_id')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN group_id INT UNSIGNED NULL AFTER role');
}

$groups = [
    ['admin', 'Sistem Admin', 0, 1],
    ['servis_muduru', 'Servis Müdürü', 10, 1],
    ['servis_mudur_yrd', 'Servis Müdür Yardımcısı', 20, 1],
    ['hasar_danismani', 'Hasar Danışmanı', 30, 1],
    ['mekanik_danismani', 'Mekanik Danışmanı', 40, 1],
    ['hasar_yoneticisi', 'Hasar Yöneticisi', 50, 1],
];

$insGroup = $pdo->prepare(
    'INSERT INTO user_groups (code, name, sort_order, is_system, is_active)
     SELECT ?, ?, ?, ?, 1 FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM user_groups WHERE code = ?)'
);
foreach ($groups as $g) {
    $insGroup->execute([$g[0], $g[1], $g[2], $g[3], $g[0]]);
}

$permSets = [
    'admin' => [
        'access_hasar', 'access_prim', 'access_admin', 'access_reports', 'access_tour',
        'hasar_create_file', 'hasar_edit_all', 'hasar_edit_own', 'hasar_status_all', 'hasar_status_limited', 'hasar_search',
        'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_team', 'prim_view_amounts', 'prim_manage_settings',
    ],
    'servis_muduru' => [
        'access_hasar', 'access_prim', 'access_admin', 'access_reports', 'access_tour',
        'hasar_create_file', 'hasar_edit_all', 'hasar_edit_own', 'hasar_status_all', 'hasar_status_limited', 'hasar_search',
        'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_team', 'prim_view_amounts', 'prim_manage_settings',
    ],
    'servis_mudur_yrd' => [
        'access_hasar', 'access_prim', 'access_reports', 'access_tour',
        'hasar_create_file', 'hasar_edit_all', 'hasar_edit_own', 'hasar_status_all', 'hasar_search',
        'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_team', 'prim_view_amounts',
    ],
    'hasar_danismani' => [
        'access_hasar', 'access_prim', 'access_tour',
        'hasar_create_file', 'hasar_edit_own', 'hasar_status_all', 'hasar_search',
        'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_amounts',
    ],
    'mekanik_danismani' => [
        'access_hasar', 'access_prim', 'access_tour',
        'hasar_status_limited', 'hasar_search',
        'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_amounts',
    ],
    'hasar_yoneticisi' => [
        'access_hasar', 'access_prim', 'access_reports', 'access_tour',
        'hasar_create_file', 'hasar_edit_all', 'hasar_edit_own', 'hasar_status_all', 'hasar_search',
        'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_team', 'prim_view_amounts',
    ],
];

$groupIds = [];
foreach ($pdo->query('SELECT id, code FROM user_groups')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $groupIds[$row['code']] = (int) $row['id'];
}

$insPerm = $pdo->prepare(
    'INSERT INTO group_permissions (group_id, perm_key, allowed)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
);

foreach ($permSets as $code => $keys) {
    $gid = $groupIds[$code] ?? 0;
    if ($gid < 1) {
        continue;
    }
    foreach ($keys as $key) {
        $insPerm->execute([$gid, $key]);
    }
}

$roleMap = [
    'admin' => 'admin',
    'manager' => 'servis_muduru',
    'advisor' => 'hasar_danismani',
    'workshop' => 'mekanik_danismani',
];
$upd = $pdo->prepare('UPDATE users SET group_id = ? WHERE group_id IS NULL AND role = ?');
foreach ($roleMap as $role => $code) {
    $gid = $groupIds[$code] ?? null;
    if ($gid) {
        $upd->execute([$gid, $role]);
    }
}

if (v15_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v15_user_groups']);
}

echo "migrate_v15: user_groups + permissions OK\n";

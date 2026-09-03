<?php
/**
 * v17: Servis Müdürü = admin paneli erişimi (access_admin + ilgili ayarlar)
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

function v17_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!v17_table_exists($pdo, 'user_groups') || !v17_table_exists($pdo, 'group_permissions')) {
    echo "migrate_v17: groups missing, skip\n";
    exit(0);
}

$stmt = $pdo->prepare('SELECT id FROM user_groups WHERE code = ? LIMIT 1');
$stmt->execute(['servis_muduru']);
$gid = (int) $stmt->fetchColumn();
if ($gid < 1) {
    echo "migrate_v17: servis_muduru not found\n";
    exit(0);
}

$keys = [
    'access_hasar', 'access_prim', 'access_admin', 'access_reports', 'access_tour',
    'hasar_create_file', 'hasar_edit_all', 'hasar_edit_own', 'hasar_status_all', 'hasar_status_limited', 'hasar_search',
    'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_team', 'prim_view_amounts', 'prim_manage_settings',
];

$ins = $pdo->prepare(
    'INSERT INTO group_permissions (group_id, perm_key, allowed)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE allowed = 1'
);
foreach ($keys as $key) {
    $ins->execute([$gid, $key]);
}

if (v17_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v17_servis_muduru_admin']);
}

echo "migrate_v17: servis_muduru admin panel access OK\n";

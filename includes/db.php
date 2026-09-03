<?php
declare(strict_types=1);

function schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function schema_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_app_migrations_table(PDO $pdo): void
{
    if (schema_table_exists($pdo, 'app_migrations')) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE app_migrations (
            name VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function migration_applied(PDO $pdo, string $name): bool
{
    ensure_app_migrations_table($pdo);
    $stmt = $pdo->prepare('SELECT 1 FROM app_migrations WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    return (bool) $stmt->fetchColumn();
}

function mark_migration_applied(PDO $pdo, string $name): void
{
    ensure_app_migrations_table($pdo);
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute([$name]);
}

function run_migration_script(string $script): void
{
    if (!is_file($script)) {
        return;
    }
    ob_start();
    try {
        require $script;
    } catch (Throwable $e) {
        error_log('OTOHASAR migration failed: ' . $e->getMessage());
    }
    ob_end_clean();
}

function ensure_schema_upgrades(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $scripts = __DIR__ . '/../scripts/';
    ensure_app_migrations_table($pdo);

    if (!schema_column_exists($pdo, 'damage_files', 'workshop_upload_until')) {
        run_migration_script($scripts . 'migrate_v3.php');
    }
    if (!schema_column_exists($pdo, 'damage_files', 'customer_upload_until')) {
        run_migration_script($scripts . 'migrate_v4.php');
    }
    if (!schema_column_exists($pdo, 'customers', 'address')
        || !schema_column_exists($pdo, 'damage_files', 'work_order_no')) {
        run_migration_script($scripts . 'migrate_v5.php');
    }
    if (!schema_column_exists($pdo, 'damage_files', 'customer_message')) {
        run_migration_script($scripts . 'migrate_v7.php');
    }
    if (!schema_column_exists($pdo, 'damage_files', 'status_changed_at')) {
        run_migration_script($scripts . 'migrate_v8.php');
    }
    if (!schema_column_exists($pdo, 'app_categories', 'description')) {
        run_migration_script($scripts . 'migrate_v9.php');
    }
    if (!schema_table_exists($pdo, 'insurance_doc_templates')) {
        run_migration_script($scripts . 'migrate_v6.php');
    } elseif (!migration_applied($pdo, 'v6_category_seed')) {
        // Mark existing installs so category seed is not re-run on every deploy
        mark_migration_applied($pdo, 'v6_category_seed');
    }
    if (!migration_applied($pdo, 'v10_category_seed')) {
        // First time after this fix: seed missing insurance categories once, then never again
        run_migration_script($scripts . 'migrate_v10.php');
    }
    if (!schema_table_exists($pdo, 'wa_templates')) {
        run_migration_script($scripts . 'migrate_v11.php');
    }
    if (!schema_column_exists($pdo, 'damage_files', 'damage_date')
        || !schema_column_exists($pdo, 'damage_files', 'vehicle_location')) {
        run_migration_script($scripts . 'migrate_v12.php');
    }
    if (!schema_column_exists($pdo, 'vehicles', 'odometer_km')) {
        run_migration_script($scripts . 'migrate_v13.php');
    }
    $hasCover = schema_table_exists($pdo, 'cover_form_fields');
    if (!$hasCover || !schema_column_exists($pdo, 'app_categories', 'form_field_code')) {
        run_migration_script($scripts . 'migrate_v14.php');
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/config.php';
    $db = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    ensure_schema_upgrades($pdo);

    return $pdo;
}

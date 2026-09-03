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
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['insurance_doc_templates']);
    if ((int) $stmt->fetchColumn() === 0) {
        run_migration_script($scripts . 'migrate_v6.php');
    }
    try {
        $hasTemlik = (int) $pdo->query(
            "SELECT COUNT(*) FROM app_categories WHERE code = 'temlik'"
        )->fetchColumn();
        if ($hasTemlik === 0) {
            run_migration_script($scripts . 'migrate_v10.php');
        }
    } catch (Throwable $e) {
        // categories table may not exist yet
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['wa_templates']);
    if ((int) $stmt->fetchColumn() === 0) {
        run_migration_script($scripts . 'migrate_v11.php');
    }
    if (!schema_column_exists($pdo, 'damage_files', 'damage_date')
        || !schema_column_exists($pdo, 'damage_files', 'vehicle_location')) {
        run_migration_script($scripts . 'migrate_v12.php');
    }
    if (!schema_column_exists($pdo, 'vehicles', 'odometer_km')) {
        run_migration_script($scripts . 'migrate_v13.php');
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['cover_form_fields']);
    $hasCover = (int) $stmt->fetchColumn() > 0;
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

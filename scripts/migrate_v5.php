<?php
declare(strict_types=1);
/**
 * Idempotent migration v5 — address, work order no, WhatsApp templates.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v5(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function table_exists_v5(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v5...\n";

if (!column_exists_v5($pdo, 'customers', 'address')) {
    $pdo->exec('ALTER TABLE customers ADD COLUMN address VARCHAR(255) NULL DEFAULT NULL AFTER phone');
    echo "OK customers.address\n";
}

if (!column_exists_v5($pdo, 'damage_files', 'work_order_no')) {
    $pdo->exec('ALTER TABLE damage_files ADD COLUMN work_order_no VARCHAR(50) NULL DEFAULT NULL AFTER file_number');
    echo "OK damage_files.work_order_no\n";
}

if (!table_exists_v5($pdo, 'app_settings')) {
    $pdo->exec(
        "CREATE TABLE app_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK app_settings\n";
}

echo "Done v5.\n";

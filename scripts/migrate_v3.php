<?php
declare(strict_types=1);
/**
 * Idempotent migration v3 — workshop time-limited upload grants.
 * Run: php scripts/migrate_v3.php
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v3(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v3...\n";

if (!column_exists_v3($pdo, 'damage_files', 'workshop_upload_until')) {
    $pdo->exec(
        'ALTER TABLE damage_files
         ADD COLUMN workshop_upload_until DATETIME NULL DEFAULT NULL AFTER note,
         ADD COLUMN workshop_upload_hours INT UNSIGNED NULL DEFAULT NULL AFTER workshop_upload_until,
         ADD COLUMN workshop_upload_granted_by INT UNSIGNED NULL DEFAULT NULL AFTER workshop_upload_hours'
    );
    echo "OK damage_files workshop upload columns\n";
} else {
    echo "skip workshop upload columns\n";
}

echo "Done v3.\n";

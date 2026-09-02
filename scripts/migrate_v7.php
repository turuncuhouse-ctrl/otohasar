<?php
declare(strict_types=1);
/**
 * Idempotent migration v7 — customer portal message field.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v7(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v7...\n";

if (!column_exists_v7($pdo, 'damage_files', 'customer_message')) {
    $pdo->exec(
        'ALTER TABLE damage_files
         ADD COLUMN customer_message TEXT NULL DEFAULT NULL AFTER customer_upload_note'
    );
    echo "OK damage_files.customer_message\n";
}

if (!column_exists_v7($pdo, 'damage_files', 'customer_message_at')) {
    $pdo->exec(
        'ALTER TABLE damage_files
         ADD COLUMN customer_message_at DATETIME NULL DEFAULT NULL AFTER customer_message'
    );
    echo "OK damage_files.customer_message_at\n";
}

echo "Done v7.\n";

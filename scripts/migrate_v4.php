<?php
declare(strict_types=1);
/**
 * Idempotent migration v4 — customer portal upload grants + nullable uploader.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v4(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v4...\n";

if (!column_exists_v4($pdo, 'damage_files', 'customer_upload_until')) {
    $pdo->exec(
        'ALTER TABLE damage_files
         ADD COLUMN customer_upload_until DATETIME NULL DEFAULT NULL AFTER workshop_upload_granted_by,
         ADD COLUMN customer_upload_hours INT UNSIGNED NULL DEFAULT NULL AFTER customer_upload_until,
         ADD COLUMN customer_upload_granted_by INT UNSIGNED NULL DEFAULT NULL AFTER customer_upload_hours,
         ADD COLUMN customer_upload_token VARCHAR(64) NULL DEFAULT NULL AFTER customer_upload_granted_by,
         ADD COLUMN customer_upload_note VARCHAR(255) NULL DEFAULT NULL AFTER customer_upload_token'
    );
    echo "OK damage_files customer upload columns\n";
} else {
    echo "skip customer upload columns\n";
}

try {
    $pdo->exec('CREATE UNIQUE INDEX uq_damage_files_customer_upload_token ON damage_files (customer_upload_token)');
    echo "OK customer_upload_token index\n";
} catch (Throwable $e) {
    echo "skip token index: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('ALTER TABLE file_documents MODIFY uploaded_by INT UNSIGNED NULL');
    echo "OK file_documents.uploaded_by nullable\n";
} catch (Throwable $e) {
    echo "skip uploaded_by: " . $e->getMessage() . "\n";
}

echo "Done v4.\n";

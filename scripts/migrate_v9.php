<?php
declare(strict_types=1);
/**
 * Idempotent migration v9 — category short descriptions.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v9(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v9...\n";

if (!column_exists_v9($pdo, 'app_categories', 'description')) {
    $pdo->exec(
        'ALTER TABLE app_categories
         ADD COLUMN description VARCHAR(255) NULL DEFAULT NULL AFTER label'
    );
    echo "OK app_categories.description\n";
}

echo "Done v9.\n";

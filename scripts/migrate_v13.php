<?php
declare(strict_types=1);
/**
 * Idempotent migration v13 — vehicle odometer (KM).
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v13(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v13...\n";

if (!column_exists_v13($pdo, 'vehicles', 'odometer_km')) {
    try {
        $pdo->exec(
            'ALTER TABLE vehicles ADD COLUMN odometer_km INT UNSIGNED NULL DEFAULT NULL AFTER color'
        );
    } catch (Throwable $e) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN odometer_km INT UNSIGNED NULL DEFAULT NULL');
    }
    echo "OK vehicles.odometer_km\n";
}

echo "Done v13.\n";

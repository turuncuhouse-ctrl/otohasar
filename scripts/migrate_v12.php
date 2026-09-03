<?php
declare(strict_types=1);
/**
 * Idempotent migration v12 — damage event details and vehicle location.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v12(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v12...\n";

$cols = [
    'damage_date' => 'DATE NULL DEFAULT NULL AFTER note',
    'damage_time' => 'TIME NULL DEFAULT NULL AFTER damage_date',
    'damage_type' => 'VARCHAR(120) NULL DEFAULT NULL AFTER damage_time',
    'damage_place' => 'VARCHAR(255) NULL DEFAULT NULL AFTER damage_type',
    'vehicle_location' => "ENUM('musteride','serviste') NULL DEFAULT NULL AFTER damage_place",
];

$after = 'note';
foreach ($cols as $name => $def) {
    if (!column_exists_v12($pdo, 'damage_files', $name)) {
        try {
            $pdo->exec("ALTER TABLE damage_files ADD COLUMN $name $def");
            echo "OK damage_files.$name\n";
        } catch (Throwable $e) {
            // fallback without AFTER if note missing
            $pdo->exec("ALTER TABLE damage_files ADD COLUMN $name " . preg_replace('/ AFTER .+$/', '', $def));
            echo "OK damage_files.$name (no AFTER)\n";
        }
    }
}

echo "Done v12.\n";

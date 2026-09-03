<?php
declare(strict_types=1);
/**
 * Idempotent migration v10 — Temlik category for insurance forms.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "Migrating v10...\n";

$ins = $pdo->prepare(
    'INSERT INTO app_categories (code, label, description, sort_order, is_required, is_active)
     SELECT ?, ?, ?, ?, ?, 1 FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM app_categories WHERE code = ?)'
);

// description column may be missing on very old DBs — fallback without it
try {
    foreach (
        [
            ['taahhut', 'Taahhüt', 'İmzalı taahhütname', 80, 0],
            ['teslim', 'Teslim', 'Araç teslim formu', 81, 0],
            ['ibra', 'İbra', 'İbra belgesi', 82, 0],
            ['temlik', 'Temlik', 'Temlik belgesi', 83, 0],
        ] as [$code, $label, $desc, $sort, $req]
    ) {
        $ins->execute([$code, $label, $desc, $sort, $req, $code]);
    }
} catch (Throwable $e) {
    $ins2 = $pdo->prepare(
        'INSERT INTO app_categories (code, label, sort_order, is_required, is_active)
         SELECT ?, ?, ?, ?, 1 FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM app_categories WHERE code = ?)'
    );
    foreach (
        [
            ['taahhut', 'Taahhüt', 80, 0],
            ['teslim', 'Teslim', 81, 0],
            ['ibra', 'İbra', 82, 0],
            ['temlik', 'Temlik', 83, 0],
        ] as [$code, $label, $sort, $req]
    ) {
        $ins2->execute([$code, $label, $sort, $req, $code]);
    }
}

echo "OK app_categories temlik (+ taahhut/teslim/ibra ensure)\n";
echo "Done v10.\n";

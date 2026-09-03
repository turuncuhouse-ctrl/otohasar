<?php
declare(strict_types=1);
/**
 * Idempotent migration v6 — KVKK consents, insurance doc templates, eksper categories.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function table_exists_v6(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function index_exists_v6(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v6...\n";

if (!table_exists_v6($pdo, 'portal_kvkk_consents')) {
    $pdo->exec(
        "CREATE TABLE portal_kvkk_consents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plate VARCHAR(20) NOT NULL,
            damage_file_id INT UNSIGNED NULL DEFAULT NULL,
            accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(45) NULL DEFAULT NULL,
            user_agent VARCHAR(255) NULL DEFAULT NULL,
            version VARCHAR(40) NOT NULL DEFAULT 'kvkk-v1',
            INDEX idx_portal_kvkk_plate (plate),
            INDEX idx_portal_kvkk_file (damage_file_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK portal_kvkk_consents\n";
}

if (!table_exists_v6($pdo, 'insurance_doc_templates')) {
    $pdo->exec(
        "CREATE TABLE insurance_doc_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            insurance_company_id INT UNSIGNED NOT NULL,
            doc_type VARCHAR(40) NOT NULL,
            title VARCHAR(120) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ins_template_type (insurance_company_id, doc_type),
            CONSTRAINT fk_ins_doc_company FOREIGN KEY (insurance_company_id)
                REFERENCES insurance_companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK insurance_doc_templates\n";
}

$cats = [
    ['taahhut', 'Taahhüt', 80, 0],
    ['teslim', 'Teslim', 81, 0],
    ['ibra', 'İbra', 82, 0],
];
if (migration_applied($pdo, 'v6_category_seed')) {
    echo "skip v6 category seed (already applied)\n";
} else {
    $ins = $pdo->prepare(
        'INSERT INTO app_categories (code, label, sort_order, is_required, is_active)
         SELECT ?, ?, ?, ?, 1 FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM app_categories WHERE code = ?)'
    );
    foreach ($cats as [$code, $label, $sort, $req]) {
        $ins->execute([$code, $label, $sort, $req, $code]);
    }
    mark_migration_applied($pdo, 'v6_category_seed');
    echo "OK app_categories taahhut/teslim/ibra (one-time seed)\n";
}

echo "Done v6.\n";

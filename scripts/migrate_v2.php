<?php
declare(strict_types=1);
/**
 * Idempotent migration v2 — admin settings, insurance, flexible status/category.
 * Run: php scripts/migrate_v2.php
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v2...\n";

// Flexible status / category columns
try {
    $pdo->exec("ALTER TABLE damage_files MODIFY status VARCHAR(50) NOT NULL DEFAULT 'evrak_bekliyor'");
    echo "OK damage_files.status\n";
} catch (Throwable $e) {
    echo "skip status: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE file_documents MODIFY category VARCHAR(50) NOT NULL");
    echo "OK file_documents.category\n";
} catch (Throwable $e) {
    echo "skip category: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('advisor','manager','workshop','admin') NOT NULL");
    echo "OK users.role\n";
} catch (Throwable $e) {
    echo "skip role: " . $e->getMessage() . "\n";
}

if (!table_exists($pdo, 'app_statuses')) {
    $pdo->exec(
        "CREATE TABLE app_statuses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            label VARCHAR(100) NOT NULL,
            color_class VARCHAR(50) NOT NULL DEFAULT 'status-slate',
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $statuses = [
        ['evrak_bekliyor', 'Evrak Bekliyor', 'status-amber', 10],
        ['eksperde', 'Eksperde', 'status-violet', 20],
        ['parca_bekliyor', 'Parça Bekliyor', 'status-blue', 30],
        ['onarimda', 'Onarımda', 'status-cyan', 40],
        ['teslime_hazir', 'Teslime Hazır', 'status-green', 50],
        ['tamamlandi', 'Tamamlandı', 'status-slate', 60],
    ];
    $ins = $pdo->prepare('INSERT INTO app_statuses (code, label, color_class, sort_order) VALUES (?,?,?,?)');
    foreach ($statuses as $s) {
        $ins->execute($s);
    }
    echo "OK app_statuses\n";
}

if (!table_exists($pdo, 'app_categories')) {
    $pdo->exec(
        "CREATE TABLE app_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            label VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $cats = [
        ['ruhsat', 'Ruhsat', 10, 1],
        ['ehliyet', 'Ehliyet', 20, 1],
        ['tutanak', 'Tutanak', 30, 0],
        ['hasar_foto', 'Hasar Foto', 40, 1],
        ['ekspertiz', 'Ekspertiz', 50, 0],
        ['onarim', 'Onarım', 60, 0],
        ['diger', 'Diğer', 70, 0],
    ];
    $ins = $pdo->prepare('INSERT INTO app_categories (code, label, sort_order, is_required) VALUES (?,?,?,?)');
    foreach ($cats as $c) {
        $ins->execute($c);
    }
    echo "OK app_categories\n";
}

if (!table_exists($pdo, 'insurance_companies')) {
    $pdo->exec(
        "CREATE TABLE insurance_companies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            labor_discount DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            parts_discount DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ins = $pdo->prepare('INSERT INTO insurance_companies (name, labor_discount, parts_discount) VALUES (?,?,?)');
    foreach ([['Anadolu Sigorta', 15, 10], ['Allianz', 12, 8], ['Axa Sigorta', 10, 10], ['Mapfre', 10, 5], ['HDI Sigorta', 8, 8], ['Groupama', 10, 7]] as $row) {
        $ins->execute($row);
    }
    echo "OK insurance_companies\n";
}

// Ensure admin user (production username). migrate_v8 renames admindemo → admin if needed.
$stmt = $pdo->prepare("SELECT id FROM users WHERE username IN ('admin', 'admindemo') LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    $pdo->prepare(
        "INSERT INTO users (name, username, role, email, phone, password) VALUES (?,?,?,?,?,?)"
    )->execute(['Sistem Admin', 'admin', 'admin', 'admin@otohasar.local', null, $hash]);
    echo "OK admin user\n";
} else {
    $pdo->exec("UPDATE users SET role='admin' WHERE username IN ('admin', 'admindemo')");
}

echo "Migration v2 complete.\n";

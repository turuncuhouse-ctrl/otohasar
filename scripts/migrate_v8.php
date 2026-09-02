<?php
declare(strict_types=1);
/**
 * Idempotent migration v8 — production cutover:
 * - status_changed_at on damage_files
 * - ensure system admin (admin / 1234 on first create only)
 * - purge demo users and demo damage data (once)
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function column_exists_v8(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function table_exists_v8(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v8...\n";

if (!table_exists_v8($pdo, 'app_settings')) {
    $pdo->exec(
        "CREATE TABLE app_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK app_settings\n";
}

try {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('advisor','manager','workshop','admin') NOT NULL");
} catch (Throwable $e) {
    // already migrated
}

if (!column_exists_v8($pdo, 'damage_files', 'status_changed_at')) {
    $pdo->exec(
        'ALTER TABLE damage_files
         ADD COLUMN status_changed_at DATETIME NULL DEFAULT NULL AFTER status'
    );
    echo "OK damage_files.status_changed_at\n";
}

// Backfill status_changed_at from latest "Durum" log, else created_at
$pdo->exec(
    "UPDATE damage_files df
     SET status_changed_at = COALESCE(
         (
             SELECT MAX(fl.created_at)
             FROM file_logs fl
             WHERE fl.damage_file_id = df.id
               AND fl.action_description LIKE 'Durum %'
         ),
         df.created_at
     )
     WHERE status_changed_at IS NULL"
);
echo "OK status_changed_at backfill\n";

// Ensure system admin user (username: admin). Password 1234 only on create / rename from demo.
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username IN ('admin', 'admindemo') ORDER BY username = 'admin' DESC LIMIT 1");
$stmt->execute();
$adminRow = $stmt->fetch();

if ($adminRow) {
    if ($adminRow['username'] === 'admindemo') {
        $hash = password_hash('1234', PASSWORD_BCRYPT);
        $pdo->prepare(
            "UPDATE users SET name=?, username=?, role='admin', email=?, password=?, is_active=1 WHERE id=?"
        )->execute(['Sistem Admin', 'admin', 'admin@otohasar.local', $hash, (int) $adminRow['id']]);
        echo "OK renamed admindemo → admin\n";
    } else {
        $pdo->prepare("UPDATE users SET role='admin', is_active=1 WHERE id=?")->execute([(int) $adminRow['id']]);
        echo "OK admin role ensured\n";
    }
} else {
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    $pdo->prepare(
        "INSERT INTO users (name, username, role, email, phone, password, is_active) VALUES (?,?,?,?,?,?,1)"
    )->execute(['Sistem Admin', 'admin', 'admin', 'admin@otohasar.local', null, $hash]);
    echo "OK created admin user\n";
}

// One-time demo purge
$flagStmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'demo_purged_v8'");
$flagStmt->execute();
$purged = $flagStmt->fetchColumn();

if ($purged !== '1') {
    $pdo->beginTransaction();
    try {
        if (table_exists_v8($pdo, 'portal_kvkk_consents')) {
            $pdo->exec('DELETE FROM portal_kvkk_consents');
        }

        // Documents/logs cascade with damage_files
        $pdo->exec('DELETE FROM damage_files');

        // Orphan demo customers/vehicles
        $pdo->exec('DELETE FROM vehicles');
        $pdo->exec('DELETE FROM customers');

        $demoUsernames = [
            'admindemo',
            'yoneticidemo',
            'hasardanismandemo',
            'hasardanisman2demo',
            'atolyedemo',
        ];
        $in = implode(',', array_fill(0, count($demoUsernames), '?'));
        $idsStmt = $pdo->prepare("SELECT id FROM users WHERE username IN ($in)");
        $idsStmt->execute($demoUsernames);
        $demoIds = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($demoIds) {
            $idPlaceholders = implode(',', array_fill(0, count($demoIds), '?'));
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id IN ($idPlaceholders)")->execute($demoIds);
            // Any leftover logs referencing demo users
            try {
                $pdo->prepare("DELETE FROM file_logs WHERE user_id IN ($idPlaceholders)")->execute($demoIds);
            } catch (Throwable $e) {
                // ignore
            }
            $pdo->prepare("DELETE FROM users WHERE id IN ($idPlaceholders)")->execute($demoIds);
        }

        $pdo->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('demo_purged_v8', '1')
             ON DUPLICATE KEY UPDATE setting_value = '1'"
        )->execute();

        $pdo->commit();
        echo "OK demo data purged\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "WARN demo purge failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "skip demo purge (already done)\n";
}

echo "Done v8.\n";

<?php
/**
 * v16: Prim satışları + tanıtım slaytları + varsayılan ayarlar
 */
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']),
    $db['user'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function v16_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function v16_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!v16_table_exists($pdo, 'app_settings')) {
    $pdo->exec(
        "CREATE TABLE app_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS prim_sales (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        damage_file_id INT UNSIGNED NULL,
        plate VARCHAR(20) NULL,
        title VARCHAR(200) NOT NULL DEFAULT '',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        quantity INT UNSIGNED NOT NULL DEFAULT 1,
        context ENUM('teslim','kabul','diger') NOT NULL DEFAULT 'diger',
        sold_by INT UNSIGNED NOT NULL,
        sale_at DATETIME NOT NULL,
        note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_prim_sold_by (sold_by),
        KEY idx_prim_sale_at (sale_at),
        KEY idx_prim_file (damage_file_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS tour_slides (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        body TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (!v16_column_exists($pdo, 'users', 'tour_seen_at')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN tour_seen_at DATETIME NULL AFTER group_id');
}

$set = $pdo->prepare(
    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_key = setting_key'
);
$defaults = [
    'prim_enabled' => '1',
    'prim_window_days' => '30',
    'prim_mode' => 'pct', // pct | fixed
    'prim_rate_pct' => '5',
    'prim_fixed_amount' => '0',
    'prim_beneficiary' => 'seller', // seller | advisor
];
foreach ($defaults as $k => $v) {
    $set->execute([$k, $v]);
}

$slideCount = (int) $pdo->query('SELECT COUNT(*) FROM tour_slides')->fetchColumn();
if ($slideCount === 0) {
    $slides = [
        [10, 'OTOHASAR’a Hoş Geldiniz', "Bu sistem hasar dosyası takibi ve ek satış (prim) kayıtlarını birlikte yönetir.\n\nÜst menüden erişebildiğiniz alanlar, kullanıcı grubunuzun izinlerine göre belirlenir."],
        [20, 'Kullanıcı Grupları', "Her kullanıcı bir gruba aittir (Servis Müdürü, Hasar Danışmanı, Mekanik Danışmanı vb.).\n\nSistem Admin, Sistem Ayarları > Kullanıcı Grupları ekranından yeni grup ekleyebilir ve her grubun neyi görüp yapabileceğini işaretleyebilir."],
        [30, 'Hasar Dosya Panosu', "Pano’da dosyaları durumlara göre filtreler, liste veya kanban görünümünde takip edersiniz.\n\nYeni dosya oluşturma, düzenleme ve durum değiştirme yetkileri grubunuza bağlıdır."],
        [40, 'Prim Sistemi', "Araç kabul veya teslim sırasında yapılan ek satışlar Prim modülüne kaydedilir.\n\nDanışman kendi satışlarını ve adetlerini görür; Servis Müdürü / Müdür Yardımcısı ve yetkili gruplar ekip toplamlarını görebilir."],
        [50, 'Prim Hesabı', "Sistem Ayarları > Prim Ayarları bölümünde süre penceresi (gün), yüzde veya sabit tutar tanımlanır.\n\nHak ediş, varsayılan olarak satışı kaydeden kullanıcıya yazılır."],
        [60, 'Tanıtım ve Destek', "Bu tanıtımı istediğiniz zaman üst menüdeki Tanıtım linkinden tekrar açabilirsiniz.\n\nŞifrenizi Hesabım / Şifre sayfasından değiştirebilirsiniz."],
    ];
    $ins = $pdo->prepare('INSERT INTO tour_slides (sort_order, title, body, is_active) VALUES (?,?,?,1)');
    foreach ($slides as $s) {
        $ins->execute([$s[0], $s[1], $s[2]]);
    }
}

if (v16_table_exists($pdo, 'app_migrations')) {
    $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v16_prim_tour']);
}

echo "migrate_v16: prim_sales + tour_slides OK\n";

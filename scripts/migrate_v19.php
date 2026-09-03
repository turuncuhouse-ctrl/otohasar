<?php
/**
 * v19: Tanıtım — profesyonel sunum içeriği + sadece admin/servis müdürü (diğerleri izinle)
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

function v19_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function v19_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!v19_table_exists($pdo, 'tour_slides')) {
    echo "migrate_v19: tour_slides missing\n";
    exit(0);
}

if (!v19_column_exists($pdo, 'tour_slides', 'eyebrow')) {
    $pdo->exec('ALTER TABLE tour_slides ADD COLUMN eyebrow VARCHAR(80) NULL AFTER title');
}
if (!v19_column_exists($pdo, 'tour_slides', 'bullets')) {
    $pdo->exec('ALTER TABLE tour_slides ADD COLUMN bullets TEXT NULL AFTER body');
}

// Tanıtım izni: yalnızca Sistem Admin + Servis Müdürü (diğer gruplar admin panelinden açılır)
if (v19_table_exists($pdo, 'user_groups') && v19_table_exists($pdo, 'group_permissions')) {
    $pdo->exec("DELETE gp FROM group_permissions gp
        INNER JOIN user_groups ug ON ug.id = gp.group_id
        WHERE gp.perm_key = 'access_tour'
          AND ug.code NOT IN ('admin', 'servis_muduru')");

    $ins = $pdo->prepare(
        'INSERT INTO group_permissions (group_id, perm_key, allowed)
         SELECT id, ?, 1 FROM user_groups WHERE code IN (?, ?)
         ON DUPLICATE KEY UPDATE allowed = 1'
    );
    $ins->execute(['access_tour', 'admin', 'servis_muduru']);
}

// Profesyonel sunum içeriğini yenile (tek seferlik)
$already = false;
if (v19_table_exists($pdo, 'app_migrations')) {
    $chk = $pdo->prepare('SELECT 1 FROM app_migrations WHERE name = ?');
    $chk->execute(['v19_tour_pro']);
    $already = (bool) $chk->fetchColumn();
}
if (!$already) {
    $pdo->exec('DELETE FROM tour_slides');
    $slides = [
        [
            10,
            'OTOHASAR ile operasyonu tek ekranda yönetin',
            'Kurumsal tanıtım',
            "OTOHASAR; hasar dosyası süreçleri, evrak takibi, müşteri iletişimi ve servis ek satış (prim) yönetimini aynı platformda birleştirir.\n\nAmaç: danışmanların günlük işini hızlandırmak, müdürlerin ise anlık görünürlük ve kontrol kazanmasını sağlamaktır.",
            "Hasar dosyasından teslime kadar uçtan uca takip\nRol bazlı yetki ile doğru kişiye doğru ekran\nEk satış ve prim ile servis cirosunu ölçülebilir kılma",
        ],
        [
            20,
            'Kim neyi görür? Grup ve yetki modeli',
            'Yönetişim',
            "Her kullanıcı bir gruba aittir. Gruplar Sistem Ayarları > Kullanıcı Grupları ekranından yönetilir.\n\nHazır gruplar: Sistem Admin, Servis Müdürü, Servis Müdür Yardımcısı, Hasar Danışmanı, Mekanik Danışmanı, Hasar Yöneticisi. İhtiyaca göre yeni grup açıp izinleri tek tek işaretleyebilirsiniz.\n\nBu tanıtım sunumu varsayılan olarak yalnızca Sistem Admin ve Servis Müdürü’nde görünür; başka gruplara ‘Tanıtım’ izni verilerek açılır.",
            "Modül erişimi: Hasar / Prim / Rapor / Ayarlar / Tanıtım\nHasar: dosya oluşturma, kendi veya tüm dosyalar, durum değiştirme\nPrim: satış kaydı, kendi/ekip görünümü, tutar görme",
        ],
        [
            30,
            'Hasar dosyasının yaşam döngüsü',
            'Hasar süreci',
            "Dosya açılışından tamamlanmaya kadar her durum panoda renkli kartlarla izlenir. Durum geçişleri yetkiye bağlıdır; örneğin atölye / mekanik danışman sınırlı geçiş yapabilirken müdür ve danışman daha geniş kontrol eder.\n\nTipik akış: evrak bekleme → ekspertiz / parça → onarım → teslime hazır → tamamlandı.",
            "Pano: liste ve kanban görünümü\nDurum filtreleri ile anlık iş yükü\nDanışman iş yükü özeti (yönetici görünümü)",
        ],
        [
            40,
            'Evrak, kapak formu ve müşteri yükleme',
            'Dokümantasyon',
            "Her dosyada kategori bazlı evrak yüklenir. İstenen kategoriler ve kapak formu alanları Sistem Ayarları’ndan özelleştirilir.\n\nMüşteriye zaman sınırlı yükleme linki verilebilir; WhatsApp şablonları ile durum ve evrak daveti tek tıkla gönderilir. Tamamlanan dosyalar için A4 kapak formu yazdırılır veya PDF kaydedilir.",
            "Kategori ↔ kapak formu eşlemesi\nSigorta şirketi şablonları ve iskontolar\nZIP ile toplu evrak indirme",
        ],
        [
            50,
            'Prim: ürün, hedef ve ekip performansı',
            'Satış & prim',
            "Araç kabul veya teslim anındaki ek satışlar Prim modülüne kaydedilir. Otomotiv servis pratiğine uygun üç katman desteklenir:\n\n1) Ürün / SPIFF — ürün başına yüzde, sabit tutar veya ek spiff\n2) Bireysel hedef — dönem kotası ve kademeli bonus (%80 / %100 / %120)\n3) Ekip hedefi — servis ekibinin ortak hedefine göre prim\n\nDanışman kendi adet ve primini görür; yetkili gruplar ekip toplamını inceler.",
            "Prim Ayarları: Genel · Ürün/SPIFF · Hedefler\nSatış kaydında ürün seçimi ve otomatik prim hesabı\nPano’da hedef ilerleme çubukları",
        ],
        [
            60,
            'Yönetim paneli ve raporlama',
            'Kontrol merkezi',
            "Sistem Ayarları (Admin / Servis Müdürü): kullanıcılar, gruplar, durumlar, kategoriler, sigorta, WhatsApp, prim ve tanıtım içeriği.\n\nRaporlar ekranı danışman iş hacmini özetler. Menüde gördüğünüz her madde, grubunuzun izin matrisinden gelir — böylece saha personeli sade, yönetim ise tam yetkili çalışır.",
            "Şifre: Hesabım sayfasından değiştirilir\nTanıtım içeriği Admin > Tanıtım Sunumu’ndan güncellenir\nYeni gruba Tanıtım izni vererek sunumu paylaşabilirsiniz",
        ],
        [
            70,
            'Sahaya çıkış checklist’i',
            'Devreye alma',
            "Kurulumu tamamlamak için sırayı izlemeniz yeterlidir. Bu sunumu ekip toplantılarında veya yeni müdür oryantasyonunda yeniden açabilirsiniz.",
            "Grupları ve kullanıcıları oluşturun\nHasar durumları / evrak kategorilerini kontrol edin\nPrim ürünleri ve ilk dönem hedefini tanımlayın\nGerekirse seçili gruplara Tanıtım izni verin\nİlk dosya ve ilk ek satışı deneyin",
        ],
    ];
    $ins = $pdo->prepare(
        'INSERT INTO tour_slides (sort_order, title, eyebrow, body, bullets, is_active) VALUES (?,?,?,?,?,1)'
    );
    foreach ($slides as $s) {
        $ins->execute([$s[0], $s[1], $s[2], $s[3], $s[4]]);
    }
    if (v19_table_exists($pdo, 'app_migrations')) {
        $pdo->prepare('INSERT IGNORE INTO app_migrations (name) VALUES (?)')->execute(['v19_tour_pro']);
    }
}

echo "migrate_v19: professional tour OK\n";

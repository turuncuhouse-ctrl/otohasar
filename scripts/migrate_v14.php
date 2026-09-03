<?php
declare(strict_types=1);
/**
 * Idempotent migration v14 — customizable cover form + category mapping.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function table_exists_v14(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists_v14(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migrating v14...\n";

if (!table_exists_v14($pdo, 'cover_form_fields')) {
    $pdo->exec(
        "CREATE TABLE cover_form_fields (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(80) NOT NULL UNIQUE,
            kind VARCHAR(20) NOT NULL DEFAULT 'text',
            section VARCHAR(40) NOT NULL,
            label VARCHAR(200) NOT NULL,
            data_key VARCHAR(50) NULL DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK cover_form_fields table\n";
}

$fields = [
    ['fld_sigortali', 'text', 'header_left', 'SİGORTALI', 'customer_name', 10],
    ['fld_plaka', 'text', 'header_left', 'PLAKA', 'plate', 20],
    ['fld_gelis_tarihi', 'text', 'header_left', 'GELİŞ TARİHİ', 'created_at', 30],
    ['fld_sigorta_sirketi', 'text', 'header_left', 'SİGORTA ŞİRKETİ', 'insurance_company', 40],
    ['fld_police_no', 'text', 'header_left', 'POLİÇE NO', 'policy_no', 50],
    ['fld_dosya_no', 'text', 'header_left', 'DOSYA NO', 'file_number', 60],
    ['fld_call_center', 'text', 'header_left', 'CALL CENTER', null, 70],
    ['fld_telefon', 'text', 'header_right', 'TELEFON', 'customer_phone', 10],
    ['fld_tc', 'text', 'header_right', 'TC', 'tc_vkn', 20],
    ['fld_email', 'text', 'header_right', 'E-MAİL', 'customer_email', 30],
    ['fld_km', 'text', 'header_right', 'KM', 'odometer_km', 40],
    ['chk_rapor_asli', 'check', 'checks_left', 'RAPOR ASLI', null, 10],
    ['chk_alkol', 'check', 'checks_left', 'ALKOL RAPORU', null, 20],
    ['chk_tutanak', 'check', 'checks_left', 'ANLAŞMALI TUTANAK', null, 30],
    ['chk_ruhsat', 'check', 'checks_left', 'RUHSAT', null, 40],
    ['chk_ehliyet', 'check', 'checks_left', 'EHLİYET', null, 50],
    ['chk_nufus', 'check', 'checks_left', 'NÜFUS CÜZDAN FOTOKOPİSİ', null, 60],
    ['chk_meslek', 'check', 'checks_left', 'MESLEK KİMLİK KARTI', null, 70],
    ['chk_police_fotokopi', 'check', 'checks_left', 'POLİÇE FOTOKOPİSİ', null, 80],
    ['chk_yakinlik', 'check', 'checks_left', 'SÜRÜCÜ İLE SİGORTALI YAKINLIĞI', null, 90],
    ['chk_taahhut', 'check', 'checks_left', 'TAAHHÜT BELGESİ', null, 100],
    ['chk_teslim_ibra_temlik', 'check', 'checks_left', 'TESLİM, İBRA VE TEMLİK BELGESİ', null, 110],
    ['chk_imza', 'check', 'checks_left', 'İMZA SİRKÜLERİ', null, 120],
    ['chk_ticaret', 'check', 'checks_left', 'TİCARET SİCİL GAZETESİ', null, 130],
    ['chk_vergi', 'check', 'checks_left', 'VERGİ LEVHASI', null, 140],
    ['chk_karsi_taraf', 'check', 'checks_left', 'KARŞI TARAFIN EHLİYET, RUHSAT, TRAFİK POLİÇESİ', null, 150],
    ['chk_olay_yeri', 'check', 'checks_left', 'OLAY YERİ FOTOĞRAFLARI', null, 160],
    ['chk_tuvturk', 'check', 'checks_left', 'ARAÇ MUAYENE TÜVTÜRK RAPORU', null, 170],
    ['chk_hastane', 'check', 'checks_left', 'SİGORTALI HASTANE RAPORLARI', null, 180],
    ['chk_vekalet', 'check', 'checks_left', 'VASİ VEYA VEKALETNAME', null, 190],
    ['fld_hasar_tarihi', 'text', 'damage', 'HASAR TARİHİ', 'damage_date', 10],
    ['fld_hasar_saati', 'text', 'damage', 'HASAR SAATİ', 'damage_time', 20],
    ['fld_hasar_sekli', 'text', 'damage', 'HASAR ŞEKLİ', 'damage_type', 30],
    ['fld_hasar_yeri', 'text', 'damage', 'HASAR YERİ', 'damage_place', 40],
    ['chk_arac_musteride', 'check', 'checks_right', 'ARAÇ MÜŞTERİDE', 'vehicle_musteride', 10],
    ['chk_ekspertiz', 'check', 'checks_right', 'ARAÇ EKSPERTİZİ', null, 20],
    ['chk_arac_karti', 'check', 'checks_right', 'ARAÇ KARTI', null, 30],
    ['chk_artes', 'check', 'checks_right', 'ARTES', null, 40],
    ['chk_pasaport', 'check', 'checks_right', 'PASAPORT GİRİŞ ÇIKIŞ SAYFALARI', null, 50],
    ['chk_masak', 'check', 'checks_right', 'MASAK EVRAĞI', null, 60],
    ['fld_tahmini_hasar', 'text', 'footer', 'TAHMİNİ HASAR', null, 10],
    ['fld_eksper_adi', 'text', 'footer', 'EKSPER ADI', null, 20],
    ['fld_notlar', 'notes', 'footer', 'NOTLAR', 'note', 30],
];

$ins = $pdo->prepare(
    'INSERT INTO cover_form_fields (code, kind, section, label, data_key, sort_order, is_active)
     SELECT ?, ?, ?, ?, ?, ?, 1 FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM cover_form_fields WHERE code = ?)'
);
foreach ($fields as $f) {
    $ins->execute([$f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[0]]);
}
echo "OK cover_form_fields seed\n";

if (table_exists_v14($pdo, 'app_categories') && !column_exists_v14($pdo, 'app_categories', 'form_field_code')) {
    $pdo->exec(
        "ALTER TABLE app_categories
         ADD COLUMN form_field_code VARCHAR(80) NULL DEFAULT NULL AFTER description"
    );
    echo "OK app_categories.form_field_code\n";
}

if (column_exists_v14($pdo, 'app_categories', 'form_field_code')) {
    $map = [
        'tutanak'   => 'chk_tutanak',
        'ruhsat'    => 'chk_ruhsat',
        'ehliyet'   => 'chk_ehliyet',
        'taahhut'   => 'chk_taahhut',
        'teslim'    => 'chk_teslim_ibra_temlik',
        'ibra'      => 'chk_teslim_ibra_temlik',
        'temlik'    => 'chk_teslim_ibra_temlik',
        'hasar_foto'=> 'chk_olay_yeri',
        'ekspertiz' => 'chk_ekspertiz',
    ];
    $upd = $pdo->prepare(
        'UPDATE app_categories SET form_field_code = ? WHERE code = ? AND (form_field_code IS NULL OR form_field_code = \'\')'
    );
    foreach ($map as $cat => $field) {
        $upd->execute([$field, $cat]);
    }
    echo "OK category form mappings\n";
}

echo "Done v14.\n";

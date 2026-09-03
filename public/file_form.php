<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$currentUser = require_auth();
$fileId = (int) ($_GET['id'] ?? 0);
$autoPrint = isset($_GET['print']);

if ($fileId <= 0) {
    header('Location: /dashboard.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT df.*, v.plate, v.brand, v.model, v.year, v.color, v.chassis_no, v.odometer_km,
            c.name AS customer_name, c.phone AS customer_phone, c.tc_vkn, c.email AS customer_email,
            u.name AS advisor_name
     FROM damage_files df
     JOIN vehicles v ON v.id = df.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     JOIN users u ON u.id = df.advisor_id
     WHERE df.id = ?'
);
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file || !can_access_file($currentUser, $file)) {
    http_response_code(403);
    echo 'Erişim reddedildi';
    exit;
}

$uploaded = intake_form_uploaded_categories($fileId);
$has = static function (string ...$codes) use ($uploaded): bool {
    foreach ($codes as $code) {
        if (isset($uploaded[$code])) {
            return true;
        }
    }
    return false;
};

$leftChecks = [
    ['RAPOR ASLI', false],
    ['ALKOL RAPORU', false],
    ['ANLAŞMALI TUTANAK', $has('tutanak')],
    ['RUHSAT', $has('ruhsat')],
    ['EHLİYET', $has('ehliyet')],
    ['NÜFUS CÜZDAN FOTOKOPİSİ', false],
    ['MESLEK KİMLİK KARTI', false],
    ['POLİÇE FOTOKOPİSİ', false],
    ['SÜRÜCÜ İLE SİGORTALI YAKINLIĞI', false],
    ['TAAHHÜT BELGESİ', $has('taahhut')],
    ['TESLİM, İBRA VE TEMLİK BELGESİ', $has('teslim', 'ibra', 'temlik')],
    ['İMZA SİRKÜLERİ', false],
    ['TİCARET SİCİL GAZETESİ', false],
    ['VERGİ LEVHASI', false],
    ['KARŞI TARAFIN EHLİYET, RUHSAT, TRAFİK POLİÇESİ', false],
    ['OLAY YERİ FOTOĞRAFLARI', $has('hasar_foto')],
    ['ARAÇ MUAYENE TÜVTÜRK RAPORU', false],
    ['SİGORTALI HASTANE RAPORLARI', false],
    ['VASİ VEYA VEKALETNAME', false],
];

$rightChecks = [
    ['ARAÇ MÜŞTERİDE', ($file['vehicle_location'] ?? '') === 'musteride'],
    ['ARAÇ EKSPERTİZİ', $has('ekspertiz')],
    ['ARAÇ KARTI', false],
    ['ARTES', false],
    ['PASAPORT GİRİŞ ÇIKIŞ SAYFALARI', false],
    ['MASAK EVRAĞI', false],
];

$fieldsLeft = [
    'SİGORTALI'       => form_plain($file['customer_name'] ?? null),
    'PLAKA'           => form_plain($file['plate'] ?? null),
    'GELİŞ TARİHİ'    => form_date_dmy($file['created_at'] ?? null),
    'SİGORTA ŞİRKETİ' => form_plain($file['insurance_company'] ?? null),
    'POLİÇE NO'       => form_plain($file['policy_no'] ?? null),
    'DOSYA NO'        => form_plain($file['file_number'] ?? null),
    'CALL CENTER'     => '',
];
$fieldsRight = [
    'TELEFON' => form_plain($file['customer_phone'] ?? null),
    'TC'      => form_plain($file['tc_vkn'] ?? null),
    'E-MAİL'  => form_plain($file['customer_email'] ?? null),
    'KM'      => form_km_plain($file['odometer_km'] ?? null),
];
$damageFields = [
    'HASAR TARİHİ' => form_date_dmy($file['damage_date'] ?? null),
    'HASAR SAATİ'  => form_plain(format_damage_time($file['damage_time'] ?? null) === '—'
        ? ''
        : format_damage_time($file['damage_time'] ?? null)),
    'HASAR ŞEKLİ'  => form_plain($file['damage_type'] ?? null),
    'HASAR YERİ'   => form_plain($file['damage_place'] ?? null),
];

$notes = form_plain($file['note'] ?? null);
$printName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $file['file_number']) . '-kapak-formu';

function form_row(string $label, string $value): void
{
    echo '<div class="f-row"><span class="f-lab">' . e($label) . ' :</span><span class="f-val">' . e($value) . '</span></div>';
}

function form_check(string $label, bool $on): void
{
    echo '<div class="chk"><span class="box' . ($on ? ' on' : '') . '" aria-hidden="true">'
        . ($on ? '✓' : '') . '</span><span>' . e($label) . '</span></div>';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($printName) ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            background: #e8edf3;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 5;
            display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;
            padding: .75rem 1rem;
            background: #0f172a; color: #fff;
        }
        .toolbar a, .toolbar button {
            appearance: none; border: 0; border-radius: 8px;
            padding: .45rem .8rem; font: inherit; cursor: pointer; text-decoration: none;
            color: #0f172a; background: #fff;
        }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .hint { margin-left: auto; font-size: .8rem; color: #cbd5e1; max-width: 28rem; }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: #fff;
            padding: 14mm 14mm 16mm;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .18);
        }
        .head {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 6px 28px;
            margin-bottom: 14px;
        }
        .f-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            min-height: 22px;
            margin: 3px 0;
        }
        .f-lab {
            font-weight: 700;
            font-size: 12.5px;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .f-val {
            flex: 1;
            border-bottom: 1px solid #222;
            min-height: 18px;
            font-size: 13px;
            padding: 0 2px 1px;
        }
        .body {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 10px 28px;
            align-items: start;
        }
        .chk {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: .01em;
            margin: 5px 0;
            line-height: 1.25;
        }
        .box {
            width: 13px; height: 13px;
            border: 1.6px solid #111;
            flex: 0 0 13px;
            margin-top: 1px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            line-height: 1;
            font-weight: 900;
        }
        .box.on { background: #111; color: #fff; border-color: #111; }
        .damage { margin-bottom: 18px; }
        .foot {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 28px;
        }
        .notes {
            grid-column: 1 / -1;
            margin-top: 4px;
        }
        .notes .f-val {
            display: block;
            min-height: 64px;
            border-bottom: none;
            border: 1px solid #222;
            padding: 6px 8px;
            white-space: pre-wrap;
        }
        @page { size: A4; margin: 10mm; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                width: auto; min-height: 0; margin: 0; padding: 0;
                box-shadow: none;
            }
        }
        @media (max-width: 820px) {
            .sheet { width: auto; min-height: 0; margin: 8px; padding: 16px; }
            .head, .body, .foot { grid-template-columns: 1fr; }
            .toolbar .hint { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="/file.php?id=<?= (int) $fileId ?>">Dosyaya dön</a>
        <button type="button" class="primary" id="btnPrint">Yazdır</button>
        <button type="button" id="btnPdf">PDF indir</button>
        <span class="hint">PDF için yazdır penceresinde “PDF olarak kaydet” / “Microsoft Print to PDF” seçin. Sunucuda sıkıştırma yapılmaz.</span>
    </div>
    <main class="sheet">
        <section class="head">
            <div>
                <?php foreach ($fieldsLeft as $lab => $val) { form_row($lab, $val); } ?>
            </div>
            <div>
                <?php foreach ($fieldsRight as $lab => $val) { form_row($lab, $val); } ?>
            </div>
        </section>
        <section class="body">
            <div>
                <?php foreach ($leftChecks as [$lab, $on]) { form_check($lab, $on); } ?>
            </div>
            <div>
                <div class="damage">
                    <?php foreach ($damageFields as $lab => $val) { form_row($lab, $val); } ?>
                </div>
                <?php foreach ($rightChecks as [$lab, $on]) { form_check($lab, $on); } ?>
            </div>
        </section>
        <section class="foot">
            <?php form_row('TAHMİNİ HASAR', ''); ?>
            <?php form_row('EKSPER ADI', ''); ?>
            <div class="notes">
                <div class="f-row" style="align-items:flex-start">
                    <span class="f-lab">NOTLAR :</span>
                </div>
                <div class="f-val"><?= e($notes) ?></div>
            </div>
        </section>
    </main>
    <script>
        function printForm() { window.print(); }
        document.getElementById('btnPrint').addEventListener('click', printForm);
        document.getElementById('btnPdf').addEventListener('click', printForm);
        <?php if ($autoPrint): ?>
        window.addEventListener('load', function() { setTimeout(printForm, 250); });
        <?php endif; ?>
    </script>
</body>
</html>

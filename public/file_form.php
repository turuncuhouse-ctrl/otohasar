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
$grouped = cover_form_grouped(true);
$catsByField = cover_form_category_map();
$printName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $file['file_number']) . '-kapak-formu';
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
        html, body { margin: 0; background: #e8edf3; }
        .toolbar {
            position: sticky; top: 0; z-index: 5;
            display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;
            padding: .75rem 1rem; background: #0f172a; color: #fff;
        }
        .toolbar a, .toolbar button {
            appearance: none; border: 0; border-radius: 8px;
            padding: .45rem .8rem; font: inherit; cursor: pointer; text-decoration: none;
            color: #0f172a; background: #fff;
        }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .hint { margin-left: auto; font-size: .8rem; color: #cbd5e1; max-width: 28rem; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
        }
        @media (max-width: 820px) {
            .toolbar .hint { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="/file.php?id=<?= (int) $fileId ?>">Dosyaya dön</a>
        <button type="button" class="primary" id="btnPrint">Yazdır</button>
        <button type="button" id="btnPdf">PDF indir</button>
        <span class="hint">PDF için yazdır penceresinde “PDF olarak kaydet” seçin.</span>
    </div>
    <?php require __DIR__ . '/../includes/cover_form_sheet.php'; ?>
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

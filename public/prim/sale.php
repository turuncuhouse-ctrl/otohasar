<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
require_perm($currentUser, 'access_prim');
if (!prim_is_enabled()) {
    http_response_code(403);
    exit('Prim sistemi kapalı');
}

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$sale = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM prim_sales WHERE id = ?');
    $stmt->execute([$id]);
    $sale = $stmt->fetch() ?: null;
    if (!$sale) {
        http_response_code(404);
        exit('Kayıt bulunamadı');
    }
    $isOwn = (int) $sale['sold_by'] === (int) $currentUser['id'];
    if (!$isOwn && !user_can($currentUser, 'prim_view_team')) {
        http_response_code(403);
        exit('Yetkisiz');
    }
}

$canCreate = user_can($currentUser, 'prim_sale_create');
$canEditOwn = user_can($currentUser, 'prim_sale_edit_own');
$editing = $sale !== null;
if ($editing) {
    $isOwn = (int) $sale['sold_by'] === (int) $currentUser['id'];
    if (!($isOwn && $canEditOwn) && !user_can($currentUser, 'prim_view_team')) {
        // view team can see but only edit own unless manage - keep edit own only
    }
    if (!($isOwn && $canEditOwn)) {
        // allow view-only redirect for non-editable
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // still show form disabled? simpler: require edit own
            if (!($isOwn && $canEditOwn)) {
                http_response_code(403);
                exit('Bu kaydı düzenleyemezsiniz');
            }
        }
    }
} else {
    require_perm($currentUser, 'prim_sale_create');
}

$pageTitle = $editing ? 'Satış Düzenle' : 'Yeni Satış';
$activeNav = 'prim';
$message = '';
$error = '';
$preFileId = (int) ($_GET['file_id'] ?? 0);
$prePlate = normalize_plate($_GET['plate'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $title = trim($_POST['title'] ?? '');
        $plate = normalize_plate($_POST['plate'] ?? '');
        $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $context = $_POST['context'] ?? 'diger';
        if (!in_array($context, ['teslim', 'kabul', 'diger'], true)) {
            $context = 'diger';
        }
        $saleAt = trim($_POST['sale_at'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $fileId = (int) ($_POST['damage_file_id'] ?? 0) ?: null;
        if ($title === '') {
            $title = $plate !== '' ? $plate . ' ek satış' : 'Ek satış';
        }
        if ($saleAt === '') {
            $saleAt = date('Y-m-d\TH:i');
        }
        $saleAtSql = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $saleAt)) ?: time());

        $soldBy = (int) $currentUser['id'];
        if (prim_setting('prim_beneficiary', 'seller') === 'advisor' && $fileId) {
            $f = $pdo->prepare('SELECT advisor_id FROM damage_files WHERE id = ?');
            $f->execute([$fileId]);
            $aid = (int) $f->fetchColumn();
            if ($aid > 0) {
                $soldBy = $aid;
            }
        }

        if ($amount < 0) {
            $error = 'Tutar geçersiz';
        } elseif ($editing) {
            if (!((int) $sale['sold_by'] === (int) $currentUser['id'] && $canEditOwn)) {
                $error = 'Yetkisiz';
            } else {
                $pdo->prepare(
                    'UPDATE prim_sales SET damage_file_id=?, plate=?, title=?, amount=?, quantity=?, context=?, sale_at=?, note=? WHERE id=?'
                )->execute([$fileId, $plate ?: null, $title, $amount, $qty, $context, $saleAtSql, $note ?: null, $id]);
                header('Location: /prim/sales.php?ok=1');
                exit;
            }
        } else {
            $pdo->prepare(
                'INSERT INTO prim_sales (damage_file_id, plate, title, amount, quantity, context, sold_by, sale_at, note)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$fileId, $plate ?: null, $title, $amount, $qty, $context, $soldBy, $saleAtSql, $note ?: null]);
            header('Location: /prim/sales.php?ok=1');
            exit;
        }
    }
}

$saleAtVal = $sale['sale_at'] ?? date('Y-m-d\TH:i');
if ($sale && isset($sale['sale_at'])) {
    $saleAtVal = date('Y-m-d\TH:i', strtotime((string) $sale['sale_at']) ?: time());
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><?= e($pageTitle) ?></h1>
    <a href="/prim/" class="btn btn-ghost btn-sm">← Prim</a>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="admin-form-card" style="max-width:520px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-group">
        <label>Başlık</label>
        <input class="form-input" name="title" value="<?= e($sale['title'] ?? '') ?>" placeholder="Örn. Cam filmi, bakım paketi">
    </div>
    <div class="form-group">
        <label>Plaka</label>
        <input class="form-input" name="plate" value="<?= e($sale['plate'] ?? $prePlate) ?>">
    </div>
    <div class="form-group">
        <label>Hasar dosya ID (opsiyonel)</label>
        <input class="form-input" type="number" name="damage_file_id" value="<?= e((string)($sale['damage_file_id'] ?? ($preFileId ?: ''))) ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Tutar (TL)</label>
            <input class="form-input" name="amount" required value="<?= e((string)($sale['amount'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label>Adet</label>
            <input class="form-input" type="number" min="1" name="quantity" value="<?= (int)($sale['quantity'] ?? 1) ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Bağlam</label>
        <select class="form-input" name="context">
            <?php foreach (['kabul' => 'Araç kabul', 'teslim' => 'Araç teslim', 'diger' => 'Diğer'] as $k => $lab): ?>
            <option value="<?= e($k) ?>" <?= ($sale['context'] ?? 'diger') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Satış tarihi</label>
        <input class="form-input" type="datetime-local" name="sale_at" value="<?= e($saleAtVal) ?>">
    </div>
    <div class="form-group">
        <label>Not</label>
        <textarea class="form-input" name="note" rows="3"><?= e($sale['note'] ?? '') ?></textarea>
    </div>
    <?php if (user_can($currentUser, 'prim_view_amounts') && isset($sale['amount'])): ?>
    <p class="form-hint">Tahmini prim: <?= e(format_money_tr(prim_calc_amount((float)$sale['amount'], (int)$sale['quantity']))) ?></p>
    <?php endif; ?>
    <button class="btn btn-primary btn-block" type="submit">Kaydet</button>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

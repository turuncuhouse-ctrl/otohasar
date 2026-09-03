<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    exit('Yetkisiz');
}

$pageTitle = 'Prim Ayarları';
$activeNav = 'admin';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        app_setting_set('prim_enabled', isset($_POST['prim_enabled']) ? '1' : '0');
        app_setting_set('prim_window_days', (string) max(0, (int) ($_POST['prim_window_days'] ?? 30)));
        $mode = $_POST['prim_mode'] ?? 'pct';
        if (!in_array($mode, ['pct', 'fixed'], true)) {
            $mode = 'pct';
        }
        app_setting_set('prim_mode', $mode);
        app_setting_set('prim_rate_pct', (string) max(0, (float) str_replace(',', '.', (string) ($_POST['prim_rate_pct'] ?? '5'))));
        app_setting_set('prim_fixed_amount', (string) max(0, (float) str_replace(',', '.', (string) ($_POST['prim_fixed_amount'] ?? '0'))));
        $ben = $_POST['prim_beneficiary'] ?? 'seller';
        if (!in_array($ben, ['seller', 'advisor'], true)) {
            $ben = 'seller';
        }
        app_setting_set('prim_beneficiary', $ben);
        $message = 'Prim ayarları kaydedildi';
    }
}

$enabled = prim_setting('prim_enabled', '1') === '1';
$window = prim_setting('prim_window_days', '30');
$mode = prim_setting('prim_mode', 'pct');
$rate = prim_setting('prim_rate_pct', '5');
$fixed = prim_setting('prim_fixed_amount', '0');
$beneficiary = prim_setting('prim_beneficiary', 'seller');

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Prim Ayarları</h1>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="admin-form-card" style="max-width:520px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label class="check-row"><input type="checkbox" name="prim_enabled" <?= $enabled ? 'checked' : '' ?>> Prim sistemi açık</label>

    <div class="form-group">
        <label>Süre penceresi (gün)</label>
        <input class="form-input" type="number" min="0" name="prim_window_days" value="<?= e($window) ?>">
        <p class="form-hint">Satış kaydının hak ediş için geçerli sayıldığı gün aralığı (bilgi amaçlı / rapor filtresi).</p>
    </div>

    <div class="form-group">
        <label>Hesaplama</label>
        <select class="form-input" name="prim_mode">
            <option value="pct" <?= $mode === 'pct' ? 'selected' : '' ?>>Satış tutarının yüzdesi</option>
            <option value="fixed" <?= $mode === 'fixed' ? 'selected' : '' ?>>Satış başına sabit tutar</option>
        </select>
    </div>

    <div class="form-group">
        <label>Yüzde (%)</label>
        <input class="form-input" name="prim_rate_pct" value="<?= e($rate) ?>">
    </div>

    <div class="form-group">
        <label>Sabit tutar (TL)</label>
        <input class="form-input" name="prim_fixed_amount" value="<?= e($fixed) ?>">
    </div>

    <div class="form-group">
        <label>Prim hak sahibi</label>
        <select class="form-input" name="prim_beneficiary">
            <option value="seller" <?= $beneficiary === 'seller' ? 'selected' : '' ?>>Satışı kaydeden kullanıcı</option>
            <option value="advisor" <?= $beneficiary === 'advisor' ? 'selected' : '' ?>>Dosya hasar danışmanı (dosya bağlıysa)</option>
        </select>
    </div>

    <button class="btn btn-primary" type="submit">Kaydet</button>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

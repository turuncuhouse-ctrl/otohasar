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
$pdo = db();
$message = '';
$error = '';
$tab = $_GET['tab'] ?? 'genel';
if (!in_array($tab, ['genel', 'urunler', 'hedefler'], true)) {
    $tab = 'genel';
}
$productStatus = $_GET['pstatus'] ?? 'all';
if (!in_array($productStatus, ['all', 'active', 'passive'], true)) {
    $productStatus = 'all';
}
$targetStatus = $_GET['tstatus'] ?? 'all';
if (!in_array($targetStatus, ['all', 'active', 'passive'], true)) {
    $targetStatus = 'all';
}

$users = $pdo->query('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF hatası';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_genel') {
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
            $prio = $_POST['prim_calc_priority'] ?? 'product_then_global';
            if (!in_array($prio, ['product_then_global', 'product_only', 'global_only'], true)) {
                $prio = 'product_then_global';
            }
            app_setting_set('prim_calc_priority', $prio);
            app_setting_set('prim_include_spiff', isset($_POST['prim_include_spiff']) ? '1' : '0');
            app_setting_set('prim_stack_target_bonus', isset($_POST['prim_stack_target_bonus']) ? '1' : '0');
            $message = 'Genel prim ayarları kaydedildi';
            $tab = 'genel';
        } elseif ($action === 'save_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $code = preg_replace('/[^A-Z0-9_]/', '', $code) ?: null;
            $category = trim($_POST['category'] ?? '') ?: null;
            $cmode = $_POST['commission_mode'] ?? 'pct';
            if (!in_array($cmode, ['pct', 'fixed', 'inherit'], true)) {
                $cmode = 'pct';
            }
            $rate = max(0, (float) str_replace(',', '.', (string) ($_POST['rate_pct'] ?? '0')));
            $fixed = max(0, (float) str_replace(',', '.', (string) ($_POST['fixed_amount'] ?? '0')));
            $spiff = max(0, (float) str_replace(',', '.', (string) ($_POST['spiff_amount'] ?? '0')));
            $sort = (int) ($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            $note = trim($_POST['note'] ?? '') ?: null;
            if ($name === '') {
                $error = 'Ürün adı zorunlu';
            } elseif ($id > 0) {
                $pdo->prepare(
                    'UPDATE prim_products SET code=?, name=?, category=?, commission_mode=?, rate_pct=?, fixed_amount=?, spiff_amount=?, sort_order=?, is_active=?, note=? WHERE id=?'
                )->execute([$code, $name, $category, $cmode, $rate, $fixed, $spiff, $sort, $active, $note, $id]);
                $message = 'Ürün güncellendi';
            } else {
                $pdo->prepare(
                    'INSERT INTO prim_products (code, name, category, commission_mode, rate_pct, fixed_amount, spiff_amount, sort_order, is_active, note)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                )->execute([$code, $name, $category, $cmode, $rate, $fixed, $spiff, $sort, $active, $note]);
                $message = 'Ürün eklendi';
            }
            $tab = 'urunler';
        } elseif ($action === 'toggle_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE prim_products SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
            $message = 'Ürün durumu güncellendi';
            $tab = 'urunler';
        } elseif ($action === 'save_target') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $scope = $_POST['scope'] ?? 'user';
            if (!in_array($scope, ['user', 'team'], true)) {
                $scope = 'user';
            }
            $userId = $scope === 'user' ? ((int) ($_POST['user_id'] ?? 0) ?: null) : null;
            $teamLabel = $scope === 'team' ? (trim($_POST['team_label'] ?? '') ?: 'Servis Ekibi') : null;
            $periodType = $_POST['period_type'] ?? 'month';
            $pStart = $_POST['period_start'] ?? date('Y-m-01');
            $pEnd = $_POST['period_end'] ?? date('Y-m-t');
            $metric = $_POST['metric'] ?? 'amount';
            if (!in_array($metric, ['amount', 'quantity', 'sales_count'], true)) {
                $metric = 'amount';
            }
            $targetVal = max(0, (float) str_replace(',', '.', (string) ($_POST['target_value'] ?? '0')));
            $bonusMode = $_POST['bonus_mode'] ?? 'fixed';
            if (!in_array($bonusMode, ['fixed', 'pct_of_sales', 'none'], true)) {
                $bonusMode = 'fixed';
            }
            $bonusVal = max(0, (float) str_replace(',', '.', (string) ($_POST['bonus_value'] ?? '0')));
            $active = isset($_POST['is_active']) ? 1 : 0;
            $note = trim($_POST['note'] ?? '') ?: null;

            if ($name === '') {
                $error = 'Hedef adı zorunlu';
            } elseif ($scope === 'user' && !$userId) {
                $error = 'Bireysel hedef için kullanıcı seçin';
            } else {
                if ($id > 0) {
                    $pdo->prepare(
                        'UPDATE prim_targets SET name=?, scope=?, user_id=?, team_label=?, period_type=?, period_start=?, period_end=?, metric=?, target_value=?, bonus_mode=?, bonus_value=?, is_active=?, note=? WHERE id=?'
                    )->execute([$name, $scope, $userId, $teamLabel, $periodType, $pStart, $pEnd, $metric, $targetVal, $bonusMode, $bonusVal, $active, $note, $id]);
                    $targetId = $id;
                    $message = 'Hedef güncellendi';
                } else {
                    $pdo->prepare(
                        'INSERT INTO prim_targets (name, scope, user_id, team_label, period_type, period_start, period_end, metric, target_value, bonus_mode, bonus_value, is_active, note)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$name, $scope, $userId, $teamLabel, $periodType, $pStart, $pEnd, $metric, $targetVal, $bonusMode, $bonusVal, $active, $note]);
                    $targetId = (int) $pdo->lastInsertId();
                    $message = 'Hedef oluşturuldu';
                }

                // Kademeler: min_pct[] / bonus_amount[] / label[]
                $pdo->prepare('DELETE FROM prim_target_tiers WHERE target_id=?')->execute([$targetId]);
                $mins = $_POST['tier_min'] ?? [];
                $bons = $_POST['tier_bonus'] ?? [];
                $labs = $_POST['tier_label'] ?? [];
                $insTier = $pdo->prepare(
                    'INSERT INTO prim_target_tiers (target_id, min_pct, bonus_amount, label, sort_order) VALUES (?,?,?,?,?)'
                );
                if (is_array($mins)) {
                    foreach ($mins as $i => $min) {
                        $minV = (float) str_replace(',', '.', (string) $min);
                        $bonV = (float) str_replace(',', '.', (string) ($bons[$i] ?? '0'));
                        $lab = trim((string) ($labs[$i] ?? ''));
                        if ($minV <= 0 && $bonV <= 0 && $lab === '') {
                            continue;
                        }
                        $insTier->execute([$targetId, $minV, $bonV, $lab ?: null, (int) $i * 10]);
                    }
                }

                $productIds = array_map('intval', $_POST['product_ids'] ?? []);
                set_prim_target_products($targetId, $productIds);
            }
            $tab = 'hedefler';
        } elseif ($action === 'toggle_target') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE prim_targets SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
            $message = 'Hedef durumu güncellendi';
            $tab = 'hedefler';
        }
    }
}

$enabled = prim_setting('prim_enabled', '1') === '1';
$window = prim_setting('prim_window_days', '30');
$mode = prim_setting('prim_mode', 'pct');
$rate = prim_setting('prim_rate_pct', '5');
$fixed = prim_setting('prim_fixed_amount', '0');
$beneficiary = prim_setting('prim_beneficiary', 'seller');
$priority = prim_setting('prim_calc_priority', 'product_then_global');
$includeSpiff = prim_setting('prim_include_spiff', '1') === '1';
$stackTarget = prim_setting('prim_stack_target_bonus', '1') === '1';

$allProducts = prim_products(false);
$products = $allProducts;
if ($productStatus === 'active') {
    $products = array_values(array_filter($products, static fn($p) => !empty($p['is_active'])));
} elseif ($productStatus === 'passive') {
    $products = array_values(array_filter($products, static fn($p) => empty($p['is_active'])));
}
$editProductId = (int) ($_GET['edit_product'] ?? 0);
$editProduct = null;
foreach ($allProducts as $p) {
    if ((int) $p['id'] === $editProductId) {
        $editProduct = $p;
        break;
    }
}

$allTargets = prim_targets(false);
$targets = $allTargets;
if ($targetStatus === 'active') {
    $targets = array_values(array_filter($targets, static fn($t) => !empty($t['is_active'])));
} elseif ($targetStatus === 'passive') {
    $targets = array_values(array_filter($targets, static fn($t) => empty($t['is_active'])));
}
$editTargetId = (int) ($_GET['edit_target'] ?? 0);
$editTarget = null;
$editTiers = [];
foreach ($allTargets as $t) {
    if ((int) $t['id'] === $editTargetId) {
        $editTarget = $t;
        $editTiers = prim_target_tiers((int) $t['id']);
        break;
    }
}
if (!$editTiers) {
    $editTiers = [
        ['min_pct' => 80, 'bonus_amount' => 0, 'label' => 'Eşik'],
        ['min_pct' => 100, 'bonus_amount' => 0, 'label' => 'Hedef'],
        ['min_pct' => 120, 'bonus_amount' => 0, 'label' => 'Üstü'],
    ];
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Prim Ayarları</h1>
        <p class="dash-sub">Otomotiv servis modeli: ürün SPIFF, bireysel / ekip hedefi, kademeli bonus</p>
    </div>
    <a href="/admin/" class="btn btn-ghost btn-sm">← Sistem Ayarları</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="prim-tabs">
    <a class="prim-tab<?= $tab === 'genel' ? ' active' : '' ?>" href="/admin/prim.php?tab=genel">Genel</a>
    <a class="prim-tab<?= $tab === 'urunler' ? ' active' : '' ?>" href="/admin/prim.php?tab=urunler">Ürün / Ek teşvik</a>
    <a class="prim-tab<?= $tab === 'hedefler' ? ' active' : '' ?>" href="/admin/prim.php?tab=hedefler">Hedefler</a>
</div>

<?php if ($tab === 'genel'): ?>
<form method="post" class="admin-form-card prim-panel">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_genel">
    <h2>Genel kurallar</h2>
    <p class="form-hint">Bayilerde sık görülen yapı: ürün satışı primi + dönem hedef bonusu. Aşağıdaki “varsayılan” oran, ürün seçilmediğinde veya ürün modu “genelden miras” olduğunda kullanılır.</p>

    <label class="check-row"><input type="checkbox" name="prim_enabled" <?= $enabled ? 'checked' : '' ?>> Prim sistemi açık</label>
    <label class="check-row"><input type="checkbox" name="prim_include_spiff" <?= $includeSpiff ? 'checked' : '' ?>> Ürün ek teşvik tutarını (adet başı ekstra TL) ekle</label>
    <label class="check-row"><input type="checkbox" name="prim_stack_target_bonus" <?= $stackTarget ? 'checked' : '' ?>> Hedef bonusunu satış primine ek olarak göster</label>

    <div class="form-group">
        <label>Rapor / dönem penceresi (gün)</label>
        <input class="form-input" type="number" min="0" name="prim_window_days" value="<?= e($window) ?>">
    </div>

    <div class="form-group">
        <label>Hesaplama önceliği</label>
        <select class="form-input" name="prim_calc_priority">
            <option value="product_then_global" <?= $priority === 'product_then_global' ? 'selected' : '' ?>>Ürün primi (yoksa genel)</option>
            <option value="product_only" <?= $priority === 'product_only' ? 'selected' : '' ?>>Sadece ürün primi</option>
            <option value="global_only" <?= $priority === 'global_only' ? 'selected' : '' ?>>Sadece genel oran (+ SPIFF)</option>
        </select>
    </div>

    <div class="form-group">
        <label>Genel hesaplama</label>
        <select class="form-input" name="prim_mode">
            <option value="pct" <?= $mode === 'pct' ? 'selected' : '' ?>>Satış tutarının yüzdesi</option>
            <option value="fixed" <?= $mode === 'fixed' ? 'selected' : '' ?>>Satış başına sabit tutar</option>
        </select>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Genel %</label>
            <input class="form-input" name="prim_rate_pct" value="<?= e($rate) ?>">
        </div>
        <div class="form-group">
            <label>Genel sabit (TL)</label>
            <input class="form-input" name="prim_fixed_amount" value="<?= e($fixed) ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Prim hak sahibi</label>
        <select class="form-input" name="prim_beneficiary">
            <option value="seller" <?= $beneficiary === 'seller' ? 'selected' : '' ?>>Satışı kaydeden</option>
            <option value="advisor" <?= $beneficiary === 'advisor' ? 'selected' : '' ?>>Dosya danışmanı</option>
        </select>
    </div>
    <button class="btn btn-primary" type="submit">Kaydet</button>
</form>
<?php endif; ?>

<?php if ($tab === 'urunler'): ?>
<div class="admin-filter-bar">
    <a class="filter-chip<?= $productStatus === 'all' ? ' active' : '' ?>" href="/admin/prim.php?tab=urunler">Tümü</a>
    <a class="filter-chip<?= $productStatus === 'active' ? ' active' : '' ?>" href="/admin/prim.php?tab=urunler&pstatus=active">Aktif</a>
    <a class="filter-chip<?= $productStatus === 'passive' ? ' active' : '' ?>" href="/admin/prim.php?tab=urunler&pstatus=passive">Pasif</a>
</div>
<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_product">
        <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= (int)$editProduct['id'] ?>"><?php endif; ?>
        <h2><?= $editProduct ? 'Ürün düzenle' : 'Ürün / ek teşvik ekle' ?></h2>
        <p class="form-hint">
            <strong>Yüzde:</strong> satış tutarının %’si &nbsp;·&nbsp;
            <strong>Sabit tutar:</strong> her adet için sabit TL &nbsp;·&nbsp;
            <strong>Genelden al:</strong> Genel sekmesindeki oran &nbsp;·&nbsp;
            <strong>Ek teşvik:</strong> bunlara ek, adet başı ekstra TL
        </p>
        <div class="form-group"><label>Ad</label><input class="form-input" name="name" required value="<?= e($editProduct['name'] ?? '') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Kod</label><input class="form-input" name="code" value="<?= e($editProduct['code'] ?? '') ?>" placeholder="CAM_FILM"></div>
            <div class="form-group"><label>Kategori</label><input class="form-input" name="category" value="<?= e($editProduct['category'] ?? '') ?>" placeholder="Aksesuar"></div>
        </div>
        <div class="form-group">
            <label>Komisyon tipi</label>
            <select class="form-input" name="commission_mode">
                <?php foreach (prim_commission_mode_labels() as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= ($editProduct['commission_mode'] ?? 'pct') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Yüzde değeri (%)</label><input class="form-input" name="rate_pct" value="<?= e((string)($editProduct['rate_pct'] ?? '0')) ?>"></div>
            <div class="form-group"><label>Sabit tutar (TL / adet)</label><input class="form-input" name="fixed_amount" value="<?= e((string)($editProduct['fixed_amount'] ?? '0')) ?>"></div>
            <div class="form-group"><label>Ek teşvik (TL / adet)</label><input class="form-input" name="spiff_amount" value="<?= e((string)($editProduct['spiff_amount'] ?? '0')) ?>"></div>
        </div>
        <div class="form-group"><label>Sıra</label><input class="form-input" type="number" name="sort_order" value="<?= (int)($editProduct['sort_order'] ?? 0) ?>"></div>
        <div class="form-group"><label>Not</label><textarea class="form-input" name="note" rows="2"><?= e($editProduct['note'] ?? '') ?></textarea></div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$editProduct || !empty($editProduct['is_active']) ? 'checked' : '' ?>> Aktif</label>
        <button class="btn btn-primary btn-block" type="submit"><?= $editProduct ? 'Kaydet' : 'Ekle' ?></button>
        <?php if ($editProduct): ?><a class="btn btn-ghost btn-block" href="/admin/prim.php?tab=urunler&pstatus=<?= e($productStatus) ?>">İptal</a><?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Ürün</th><th>Komisyon tipi</th><th>Oran</th><th>Ek teşvik</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            <?php if (!$products): ?>
            <tr><td colspan="6">Bu filtrede ürün yok.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $p): ?>
            <tr class="<?= empty($p['is_active']) ? 'row-passive' : '' ?>">
                <td>
                    <strong><?= e($p['name']) ?></strong>
                    <?php if ($p['category']): ?><br><span class="muted"><?= e($p['category']) ?></span><?php endif; ?>
                </td>
                <td><?= e(prim_commission_mode_short((string)$p['commission_mode'])) ?></td>
                <td>
                    <?php if ($p['commission_mode'] === 'pct'): ?>%<?= e(rtrim(rtrim((string)$p['rate_pct'], '0'), '.')) ?>
                    <?php elseif ($p['commission_mode'] === 'fixed'): ?><?= e(format_money_tr((float)$p['fixed_amount'])) ?>
                    <?php else: ?>Genel ayar<?php endif; ?>
                </td>
                <td><?= (float)$p['spiff_amount'] > 0 ? e(format_money_tr((float)$p['spiff_amount'])) : '—' ?></td>
                <td><span class="status-pill small <?= !empty($p['is_active']) ? 'status-green' : 'status-slate' ?>"><?= !empty($p['is_active']) ? 'Aktif' : 'Pasif' ?></span></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-ghost" href="/admin/prim.php?tab=urunler&pstatus=<?= e($productStatus) ?>&edit_product=<?= (int)$p['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_product">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit"><?= !empty($p['is_active']) ? 'Pasife al' : 'Aktifleştir' ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'hedefler'): ?>
<div class="admin-filter-bar">
    <a class="filter-chip<?= $targetStatus === 'all' ? ' active' : '' ?>" href="/admin/prim.php?tab=hedefler">Tümü</a>
    <a class="filter-chip<?= $targetStatus === 'active' ? ' active' : '' ?>" href="/admin/prim.php?tab=hedefler&tstatus=active">Aktif</a>
    <a class="filter-chip<?= $targetStatus === 'passive' ? ' active' : '' ?>" href="/admin/prim.php?tab=hedefler&tstatus=passive">Pasif</a>
</div>
<div class="admin-layout">
    <form method="post" class="admin-form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_target">
        <?php if ($editTarget): ?><input type="hidden" name="id" value="<?= (int)$editTarget['id'] ?>"><?php endif; ?>
        <h2><?= $editTarget ? 'Hedef düzenle' : 'Hedef tanımla' ?></h2>
        <p class="form-hint">Kişiye bireysel hedef koyun; isteğe bağlı olarak yalnızca seçili ürünlerdeki satışlar sayılsın. Hiç ürün seçmezseniz tüm satışlar dahildir.</p>
        <div class="form-group"><label>Ad</label><input class="form-input" name="name" required value="<?= e($editTarget['name'] ?? '') ?>" placeholder="Ahmet — cam filmi Mart hedefi"></div>
        <div class="form-group">
            <label>Kapsam</label>
            <select class="form-input" name="scope" id="targetScope">
                <option value="user" <?= ($editTarget['scope'] ?? 'user') === 'user' ? 'selected' : '' ?>>Bireysel (kişiye özel)</option>
                <option value="team" <?= ($editTarget['scope'] ?? '') === 'team' ? 'selected' : '' ?>>Ekip</option>
            </select>
        </div>
        <div class="form-group" id="targetUserWrap">
            <label>Kullanıcı</label>
            <select class="form-input" name="user_id">
                <option value="">— Kişi seçin —</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= (int)($editTarget['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="targetTeamWrap" style="display:none">
            <label>Ekip adı</label>
            <input class="form-input" name="team_label" value="<?= e($editTarget['team_label'] ?? 'Servis Ekibi') ?>">
        </div>
        <?php
        $editProductIds = $editTarget ? ($editTarget['_product_ids'] ?? prim_target_product_ids((int)$editTarget['id'])) : [];
        $activeProducts = prim_products(true);
        ?>
        <div class="form-group">
            <label>Hedefe dahil ürünler</label>
            <p class="form-hint" style="margin-top:0">İşaretlenen ürünlerdeki satışlar hedefe yazılır. Boş bırakılırsa tüm ürünler / serbest satışlar sayılır.</p>
            <div class="perm-section" style="max-height:220px;overflow:auto">
                <?php if (!$activeProducts): ?>
                <p class="muted">Önce Ürün sekmesinden ürün ekleyin.</p>
                <?php else: ?>
                <?php foreach ($activeProducts as $ap): ?>
                <label class="check-row">
                    <input type="checkbox" name="product_ids[]" value="<?= (int)$ap['id'] ?>"
                        <?= in_array((int)$ap['id'], $editProductIds, true) ? 'checked' : '' ?>>
                    <?= e($ap['name']) ?><?= $ap['category'] ? ' · ' . e($ap['category']) : '' ?>
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Başlangıç</label><input class="form-input" type="date" name="period_start" required value="<?= e($editTarget['period_start'] ?? date('Y-m-01')) ?>"></div>
            <div class="form-group"><label>Bitiş</label><input class="form-input" type="date" name="period_end" required value="<?= e($editTarget['period_end'] ?? date('Y-m-t')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Metrik</label>
                <select class="form-input" name="metric">
                    <?php foreach (prim_metric_labels() as $mk => $mlab): ?>
                    <option value="<?= e($mk) ?>" <?= ($editTarget['metric'] ?? 'amount') === $mk ? 'selected' : '' ?>><?= e($mlab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Hedef değeri</label><input class="form-input" name="target_value" required value="<?= e((string)($editTarget['target_value'] ?? '')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Hedef tutunca (kademe yoksa)</label>
                <select class="form-input" name="bonus_mode">
                    <option value="fixed" <?= ($editTarget['bonus_mode'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Sabit TL bonus</option>
                    <option value="pct_of_sales" <?= ($editTarget['bonus_mode'] ?? '') === 'pct_of_sales' ? 'selected' : '' ?>>Gerçekleşen satışın %</option>
                    <option value="none" <?= ($editTarget['bonus_mode'] ?? '') === 'none' ? 'selected' : '' ?>>Yok (sadece kademe)</option>
                </select>
            </div>
            <div class="form-group"><label>Bonus değeri</label><input class="form-input" name="bonus_value" value="<?= e((string)($editTarget['bonus_value'] ?? '0')) ?>"></div>
        </div>

        <h3 class="section-title">Kademeler</h3>
        <div class="tier-rows">
            <?php foreach ($editTiers as $i => $tier): ?>
            <div class="form-row tier-row">
                <div class="form-group"><label>Min %</label><input class="form-input" name="tier_min[]" value="<?= e((string)($tier['min_pct'] ?? '')) ?>"></div>
                <div class="form-group"><label>Bonus TL</label><input class="form-input" name="tier_bonus[]" value="<?= e((string)($tier['bonus_amount'] ?? '')) ?>"></div>
                <div class="form-group"><label>Etiket</label><input class="form-input" name="tier_label[]" value="<?= e($tier['label'] ?? '') ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">Boş satırlar kaydedilmez. En yüksek aşılan kademenin bonusu uygulanır.</p>

        <div class="form-group"><label>Not</label><textarea class="form-input" name="note" rows="2"><?= e($editTarget['note'] ?? '') ?></textarea></div>
        <label class="check-row"><input type="checkbox" name="is_active" <?= !$editTarget || !empty($editTarget['is_active']) ? 'checked' : '' ?>> Aktif</label>
        <input type="hidden" name="period_type" value="custom">
        <button class="btn btn-primary btn-block" type="submit"><?= $editTarget ? 'Kaydet' : 'Oluştur' ?></button>
        <?php if ($editTarget): ?><a class="btn btn-ghost btn-block" href="/admin/prim.php?tab=hedefler">İptal</a><?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="report-table">
            <thead><tr><th>Hedef</th><th>Kapsam</th><th>Dönem</th><th>İlerleme</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($targets as $t):
                $prog = prim_period_progress($t);
            ?>
            <tr>
                <td>
                    <strong><?= e($t['name']) ?></strong>
                    <?= empty($t['is_active']) ? ' <em>(pasif)</em>' : '' ?>
                    <br><span class="muted">Hedef: <?= e((string)$t['target_value']) ?> · <?= e(prim_metric_labels()[$t['metric']] ?? $t['metric']) ?></span>
                    <?php if (!empty($t['_product_names'])): ?>
                    <br><span class="muted">Ürün: <?= e(implode(', ', $t['_product_names'])) ?></span>
                    <?php else: ?>
                    <br><span class="muted">Ürün: Tümü</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($t['scope'] === 'team'): ?>
                    Ekip · <?= e($t['team_label'] ?: '—') ?>
                    <?php else: ?>
                    <?= e($t['user_name'] ?? ('#' . $t['user_id'])) ?>
                    <?php endif; ?>
                </td>
                <td><?= e($t['period_start']) ?> → <?= e($t['period_end']) ?></td>
                <td>
                    <?= e(number_format($prog['pct'], 1, ',', '.')) ?>%
                    <?php if ($prog['bonus'] > 0): ?><br><span class="muted">Bonus <?= e(format_money_tr($prog['bonus'])) ?></span><?php endif; ?>
                </td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-ghost" href="/admin/prim.php?tab=hedefler&tstatus=<?= e($targetStatus) ?>&edit_target=<?= (int)$t['id'] ?>">Düzenle</a>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_target">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit"><?= !empty($t['is_active']) ? 'Pasife al' : 'Aktifleştir' ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
ob_start();
?>
(function(){
    var scope = document.getElementById('targetScope');
    var u = document.getElementById('targetUserWrap');
    var t = document.getElementById('targetTeamWrap');
    function sync(){
        if (!scope) return;
        var team = scope.value === 'team';
        if (u) u.style.display = team ? 'none' : '';
        if (t) t.style.display = team ? '' : 'none';
    }
    if (scope) { scope.addEventListener('change', sync); sync(); }
})();
<?php
$pageScript = ob_get_clean();
endif;

require __DIR__ . '/../../includes/footer.php';
?>

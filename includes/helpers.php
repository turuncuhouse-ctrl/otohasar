<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $kind, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['_flash'] = ['kind' => $kind, 'message' => $message];
}

function flash_take(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($flash) ? $flash : null;
}

function admin_redirect(string $path): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $path, true, 303);
    exit;
}

/** Format datetime for UI: 02.09.2026 23:15 */
function format_datetime_tr(?string $value): string
{
    if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }
    return date('d.m.Y H:i', $ts);
}

/** Short date for dense lists: 02.09.26 23:15 */
function format_datetime_short(?string $value): string
{
    if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }
    return date('d.m.y H:i', $ts);
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400): never
{
    json_response(['ok' => false, 'error' => $message], $code);
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    return $config;
}

function asset_version(): string
{
    return (string) (app_config()['app']['asset_version'] ?? '7');
}

function asset_js_url(): string
{
    return '/assets/js/app.' . asset_version() . '.js';
}

function asset_css_url(): string
{
    return '/assets/css/style.' . asset_version() . '.css';
}

function status_labels(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $defaults = [
        'evrak_bekliyor' => 'Evrak Bekliyor',
        'eksperde'       => 'Eksperde',
        'parca_bekliyor' => 'Parça Bekliyor',
        'onarimda'       => 'Onarımda',
        'teslime_hazir'  => 'Teslime Hazır',
        'tamamlandi'     => 'Tamamlandı',
    ];
    try {
        $rows = db()->query(
            'SELECT code, label FROM app_statuses WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
        if ($rows) {
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['code']] = $r['label'];
            }
            return $cache;
        }
    } catch (Throwable $e) {
        // table may not exist yet
    }
    $cache = $defaults;
    return $cache;
}

function status_colors(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $defaults = [
        'evrak_bekliyor' => 'status-amber',
        'eksperde'       => 'status-violet',
        'parca_bekliyor' => 'status-blue',
        'onarimda'       => 'status-cyan',
        'teslime_hazir'  => 'status-green',
        'tamamlandi'     => 'status-slate',
    ];
    try {
        $rows = db()->query(
            'SELECT code, color_class FROM app_statuses WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
        if ($rows) {
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['code']] = $r['color_class'] ?: 'status-slate';
            }
            return $cache;
        }
    } catch (Throwable $e) {
    }
    $cache = $defaults;
    return $cache;
}

/** Available status color classes for admin palette. */
function status_color_palette(): array
{
    return [
        'status-amber'  => 'Amber',
        'status-orange' => 'Turuncu',
        'status-rose'   => 'Gül',
        'status-violet' => 'Mor',
        'status-indigo' => 'Indigo',
        'status-blue'   => 'Mavi',
        'status-cyan'   => 'Camgöbeği',
        'status-teal'   => 'Turkuaz',
        'status-green'  => 'Yeşil',
        'status-lime'   => 'Lime',
        'status-slate'  => 'Gri',
        'status-zinc'   => 'Koyu gri',
    ];
}

function category_labels(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $defaults = [
        'ruhsat'     => 'Ruhsat',
        'ehliyet'    => 'Ehliyet',
        'tutanak'    => 'Tutanak',
        'hasar_foto' => 'Hasar Foto',
        'ekspertiz'  => 'Ekspertiz',
        'taahhut'    => 'Taahhüt',
        'teslim'     => 'Teslim',
        'ibra'       => 'İbra',
        'onarim'     => 'Onarım',
        'diger'      => 'Diğer',
    ];
    try {
        $rows = db()->query(
            'SELECT code, label FROM app_categories WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
        if ($rows) {
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['code']] = $r['label'];
            }
            return $cache;
        }
    } catch (Throwable $e) {
    }
    $cache = $defaults;
    return $cache;
}

/** Short descriptions under category labels (code => text). */
function category_descriptions(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $rows = db()->query(
            'SELECT code, description FROM app_categories WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
        foreach ($rows as $r) {
            $desc = trim((string) ($r['description'] ?? ''));
            if ($desc !== '') {
                $cache[$r['code']] = $desc;
            }
        }
    } catch (Throwable $e) {
        // column may not exist yet
    }
    return $cache;
}

function role_labels(): array
{
    return [
        'admin'    => 'Sistem Admin',
        'manager'  => 'Servis Yöneticisi',
        'advisor'  => 'Hasar Danışmanı',
        'workshop' => 'Atölye Personeli',
    ];
}

function permission_catalog(): array
{
    return [
        'Modüller' => [
            'access_hasar'   => 'Hasar panosu',
            'access_prim'    => 'Prim sistemi',
            'access_admin'   => 'Sistem ayarları',
            'access_reports' => 'Raporlar',
            'access_tour'    => 'Tanıtım sunumu (yönetim / oryantasyon)',
        ],
        'Hasar' => [
            'hasar_create_file'    => 'Dosya oluştur',
            'hasar_edit_all'       => 'Tüm dosyaları düzenle',
            'hasar_edit_own'       => 'Kendi dosyalarını düzenle',
            'hasar_status_all'     => 'Tüm durumları değiştir',
            'hasar_status_limited' => 'Sınırlı durum (onarım ↔ teslime hazır)',
            'hasar_search'         => 'Dosya ara',
        ],
        'Prim' => [
            'prim_sale_create'     => 'Satış kaydet',
            'prim_sale_edit_own'   => 'Kendi satışını düzenle',
            'prim_view_own'        => 'Kendi satışlarını gör',
            'prim_view_team'       => 'Ekip satış özetini gör',
            'prim_view_amounts'    => 'Tutar / prim tutarlarını gör',
            'prim_manage_settings' => 'Prim ayarlarını yönet',
        ],
    ];
}

function all_permission_keys(): array
{
    $keys = [];
    foreach (permission_catalog() as $group) {
        foreach ($group as $k => $_label) {
            $keys[] = $k;
        }
    }
    return $keys;
}

function legacy_role_for_group_code(string $code): string
{
    return match ($code) {
        'admin' => 'admin',
        'servis_muduru', 'servis_mudur_yrd', 'hasar_yoneticisi' => 'manager',
        'mekanik_danismani' => 'workshop',
        default => 'advisor',
    };
}

function user_groups(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM user_groups';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function user_group_by_id(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function group_permission_map(int $groupId): array
{
    $map = [];
    try {
        $stmt = db()->prepare('SELECT perm_key, allowed FROM group_permissions WHERE group_id = ?');
        $stmt->execute([$groupId]);
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['perm_key']] = (int) $row['allowed'] === 1;
        }
    } catch (Throwable $e) {
        $map = [];
    }
    return $map;
}

function legacy_role_permissions(string $role): array
{
    $all = array_fill_keys(all_permission_keys(), false);
    $sets = [
        'admin' => all_permission_keys(),
        'manager' => [
            'access_hasar', 'access_prim', 'access_admin', 'access_reports', 'access_tour',
            'hasar_create_file', 'hasar_edit_all', 'hasar_edit_own', 'hasar_status_all', 'hasar_status_limited', 'hasar_search',
            'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_team', 'prim_view_amounts', 'prim_manage_settings',
        ],
        'advisor' => [
            'access_hasar', 'access_prim',
            'hasar_create_file', 'hasar_edit_own', 'hasar_status_all', 'hasar_search',
            'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_amounts',
        ],
        'workshop' => [
            'access_hasar', 'access_prim',
            'hasar_status_limited', 'hasar_search',
            'prim_sale_create', 'prim_sale_edit_own', 'prim_view_own', 'prim_view_amounts',
        ],
    ];
    foreach ($sets[$role] ?? [] as $k) {
        $all[$k] = true;
    }
    return $all;
}

function user_permissions(array $user): array
{
    $gid = isset($user['group_id']) ? (int) $user['group_id'] : 0;
    if ($gid > 0) {
        $map = group_permission_map($gid);
        $out = array_fill_keys(all_permission_keys(), false);
        foreach ($map as $k => $allowed) {
            $out[$k] = $allowed;
        }
        return $out;
    }
    return legacy_role_permissions((string) ($user['role'] ?? ''));
}

function user_can(array $user, string $perm): bool
{
    $perms = user_permissions($user);
    return !empty($perms[$perm]);
}

function user_group_label(array $user): string
{
    $gid = isset($user['group_id']) ? (int) $user['group_id'] : 0;
    if ($gid > 0) {
        $g = user_group_by_id($gid);
        if ($g) {
            return (string) $g['name'];
        }
    }
    $role = (string) ($user['role'] ?? '');
    return role_labels()[$role] ?? $role;
}

function user_home_url(array $user): string
{
    if (user_can($user, 'access_hasar')) {
        return '/dashboard.php';
    }
    if (user_can($user, 'access_prim')) {
        return '/prim/';
    }
    if (user_can($user, 'access_admin')) {
        return '/admin/';
    }
    if (user_can($user, 'access_tour')) {
        return '/tour.php';
    }
    return '/profile.php';
}

function is_admin_user(array $user): bool
{
    // Sistem Admin veya access_admin izni olan gruplar (örn. Servis Müdürü)
    return user_can($user, 'access_admin') || ($user['role'] ?? '') === 'admin';
}

function is_system_founder(array $user): bool
{
    $gid = isset($user['group_id']) ? (int) $user['group_id'] : 0;
    if ($gid > 0) {
        $g = user_group_by_id($gid);
        if ($g && ($g['code'] ?? '') === 'admin') {
            return true;
        }
    }
    return ($user['role'] ?? '') === 'admin';
}

function is_manager_user(array $user): bool
{
    return user_can($user, 'access_reports')
        || user_can($user, 'hasar_edit_all')
        || ($user['role'] ?? '') === 'manager';
}

function set_group_permissions(int $groupId, array $allowedKeys): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM group_permissions WHERE group_id = ?')->execute([$groupId]);
    $ins = $pdo->prepare('INSERT INTO group_permissions (group_id, perm_key, allowed) VALUES (?,?,1)');
    foreach ($allowedKeys as $key) {
        if (in_array($key, all_permission_keys(), true)) {
            $ins->execute([$groupId, $key]);
        }
    }
}

function prim_setting(string $key, string $default = ''): string
{
    return (string) (app_setting($key, $default) ?? $default);
}

function prim_is_enabled(): bool
{
    return prim_setting('prim_enabled', '1') === '1';
}

function prim_commission_mode_labels(): array
{
    return [
        'pct' => 'Yüzde (%) — satış tutarının yüzdesi',
        'fixed' => 'Sabit tutar — her adet için sabit TL',
        'inherit' => 'Genelden al — Genel sekmesindeki oran/sabit',
    ];
}

function prim_commission_mode_short(string $mode): string
{
    return match ($mode) {
        'pct' => 'Yüzde',
        'fixed' => 'Sabit tutar',
        'inherit' => 'Genelden al',
        default => $mode,
    };
}

function prim_metric_labels(): array
{
    return [
        'amount' => 'Satış tutarı (TL)',
        'quantity' => 'Satılan adet',
        'sales_count' => 'Satış kaydı sayısı',
    ];
}

function prim_products(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM prim_products';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, name';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function prim_product_by_id(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM prim_products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Genel (global) satış primi — ürün yoksa veya inherit */
function prim_calc_global(float $saleAmount, int $quantity = 1): float
{
    $mode = prim_setting('prim_mode', 'pct');
    if ($mode === 'fixed') {
        return round((float) prim_setting('prim_fixed_amount', '0') * max(1, $quantity), 2);
    }
    $pct = (float) prim_setting('prim_rate_pct', '5');
    return round($saleAmount * $pct / 100, 2);
}

/**
 * Tek satış için prim (ürün + SPIFF + global öncelik).
 * @param array|null $product prim_products satırı
 */
function prim_calc_sale_row(float $saleAmount, int $quantity = 1, ?array $product = null): float
{
    $qty = max(1, $quantity);
    $priority = prim_setting('prim_calc_priority', 'product_then_global');
    $includeSpiff = prim_setting('prim_include_spiff', '1') === '1';

    $productPart = 0.0;
    $globalPart = 0.0;
    $spiff = 0.0;

    if ($product) {
        $mode = (string) ($product['commission_mode'] ?? 'inherit');
        if ($mode === 'pct') {
            $productPart = round($saleAmount * ((float) ($product['rate_pct'] ?? 0)) / 100, 2);
        } elseif ($mode === 'fixed') {
            $productPart = round(((float) ($product['fixed_amount'] ?? 0)) * $qty, 2);
        } elseif ($mode === 'inherit') {
            $productPart = prim_calc_global($saleAmount, $qty);
        }
        if ($includeSpiff) {
            $spiff = round(((float) ($product['spiff_amount'] ?? 0)) * $qty, 2);
        }
    }

    if ($priority === 'global_only' || (!$product && $priority !== 'product_only')) {
        $globalPart = prim_calc_global($saleAmount, $qty);
    } elseif ($priority === 'product_then_global' && $product) {
        $mode = (string) ($product['commission_mode'] ?? 'inherit');
        if ($mode === 'inherit') {
            // already in productPart
            $globalPart = 0.0;
        } else {
            // ürün primi + opsiyonel global yok — sadece ürün
            $globalPart = 0.0;
        }
    } elseif ($priority === 'product_only') {
        if (!$product) {
            $globalPart = 0.0;
        }
    }

    if ($priority === 'global_only') {
        return round($globalPart + $spiff, 2);
    }
    if ($priority === 'product_only') {
        return round($productPart + $spiff, 2);
    }
    // product_then_global
    if ($product) {
        return round($productPart + $spiff, 2);
    }
    return round(prim_calc_global($saleAmount, $qty), 2);
}

/** Geriye uyumluluk */
function prim_calc_amount(float $saleAmount, int $quantity = 1, ?int $productId = null): float
{
    $product = $productId ? prim_product_by_id($productId) : null;
    return prim_calc_sale_row($saleAmount, $quantity, $product);
}

function prim_targets(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT t.*, u.name AS user_name FROM prim_targets t
                LEFT JOIN users u ON u.id = t.user_id';
        if ($activeOnly) {
            $sql .= ' WHERE t.is_active = 1';
        }
        $sql .= ' ORDER BY t.period_end DESC, t.id DESC';
        $rows = db()->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['_product_ids'] = prim_target_product_ids((int) $row['id']);
            $row['_product_names'] = prim_target_product_names((int) $row['id']);
        }
        unset($row);
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function prim_target_product_ids(int $targetId): array
{
    try {
        $stmt = db()->prepare('SELECT product_id FROM prim_target_products WHERE target_id = ?');
        $stmt->execute([$targetId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

function prim_target_product_names(int $targetId): array
{
    try {
        $stmt = db()->prepare(
            'SELECT p.name FROM prim_target_products tp
             JOIN prim_products p ON p.id = tp.product_id
             WHERE tp.target_id = ?
             ORDER BY p.sort_order, p.name'
        );
        $stmt->execute([$targetId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

function set_prim_target_products(int $targetId, array $productIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM prim_target_products WHERE target_id = ?')->execute([$targetId]);
    $ins = $pdo->prepare('INSERT INTO prim_target_products (target_id, product_id) VALUES (?,?)');
    foreach (array_unique(array_map('intval', $productIds)) as $pid) {
        if ($pid > 0) {
            $ins->execute([$targetId, $pid]);
        }
    }
}

function prim_target_tiers(int $targetId): array
{
    try {
        $stmt = db()->prepare('SELECT * FROM prim_target_tiers WHERE target_id = ? ORDER BY min_pct, id');
        $stmt->execute([$targetId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function prim_period_progress(array $target): array
{
    $pdo = db();
    $start = $target['period_start'] . ' 00:00:00';
    $end = $target['period_end'];
    $metric = $target['metric'] ?? 'amount';
    $scope = $target['scope'] ?? 'user';
    $productIds = $target['_product_ids'] ?? prim_target_product_ids((int) $target['id']);

    $select = match ($metric) {
        'quantity' => 'COALESCE(SUM(quantity),0)',
        'sales_count' => 'COUNT(*)',
        default => 'COALESCE(SUM(amount),0)',
    };

    $where = 'sale_at >= ? AND sale_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params = [$start, $end];
    if ($scope === 'user' && !empty($target['user_id'])) {
        $where .= ' AND sold_by = ?';
        $params[] = (int) $target['user_id'];
    }
    if ($productIds) {
        $ph = implode(',', array_fill(0, count($productIds), '?'));
        $where .= " AND product_id IN ($ph)";
        foreach ($productIds as $pid) {
            $params[] = $pid;
        }
    }

    $stmt = $pdo->prepare("SELECT {$select} FROM prim_sales WHERE {$where}");
    $stmt->execute($params);
    $actual = (float) $stmt->fetchColumn();
    $goal = (float) ($target['target_value'] ?? 0);
    $pct = $goal > 0 ? round($actual / $goal * 100, 2) : 0.0;

    $tiers = prim_target_tiers((int) $target['id']);
    $tierBonus = 0.0;
    $tierLabel = null;
    foreach ($tiers as $tier) {
        if ($pct + 0.0001 >= (float) $tier['min_pct']) {
            $tierBonus = (float) $tier['bonus_amount'];
            $tierLabel = $tier['label'] ?: ('%' . rtrim(rtrim((string) $tier['min_pct'], '0'), '.'));
        }
    }

    $flatBonus = 0.0;
    if ($tierBonus <= 0 && ($target['bonus_mode'] ?? 'none') !== 'none' && $pct >= 100) {
        if (($target['bonus_mode'] ?? '') === 'fixed') {
            $flatBonus = (float) ($target['bonus_value'] ?? 0);
        } elseif (($target['bonus_mode'] ?? '') === 'pct_of_sales') {
            $flatBonus = round($actual * ((float) ($target['bonus_value'] ?? 0)) / 100, 2);
        }
    }

    return [
        'actual' => $actual,
        'goal' => $goal,
        'pct' => $pct,
        'bonus' => $tierBonus > 0 ? $tierBonus : $flatBonus,
        'tier_label' => $tierLabel,
    ];
}

function format_money_tr(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' TL';
}

/** Giriş / kayıt için telefonu sadece rakamlara indirger (TR: 05xx → 905xx). */
function normalize_login_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
        $digits = '90' . $digits;
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '05')) {
        $digits = '90' . substr($digits, 1);
    }
    return $digits;
}

function format_phone_display(?string $phone): string
{
    if ($phone === null || trim($phone) === '') {
        return '';
    }
    $n = normalize_login_phone($phone);
    if (strlen($n) === 12 && str_starts_with($n, '90')) {
        return '+90 ' . substr($n, 2, 3) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8, 2) . ' ' . substr($n, 10, 2);
    }
    return $phone;
}

function tour_slides(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM tour_slides';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Şu an gösterilecek duyurular (aktif + süre içinde). */
function active_announcements(): array
{
    try {
        $stmt = db()->query(
            "SELECT * FROM app_announcements
             WHERE is_active = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order, id DESC
             LIMIT 10"
        );
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function announcements_all(bool $activeOnly = false): array
{
    try {
        $sql = 'SELECT * FROM app_announcements';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id DESC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function insurance_companies(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM insurance_companies';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function insurance_form_doc_types(): array
{
    // Suggested titles only — templates are free-form per company
    return [
        'taahhut' => 'Taahhüt',
        'teslim'  => 'Teslim',
        'ibra'    => 'İbra',
        'temlik'  => 'Temlik',
        'birlesik' => 'Taahhüt + Teslim + İbra (tek PDF)',
    ];
}

function vehicle_location_labels(): array
{
    return [
        'serviste'  => 'Serviste',
        'musteride' => 'Müşteride',
    ];
}

function damage_type_options(): array
{
    return [
        'Çarpışma',
        'Park hasarı',
        'Cam kırılması',
        'Hırsızlık / zorla açma',
        'Doğal afet',
        'Hayvan çarpması',
        'Yanma',
        'Diğer',
    ];
}

function parse_damage_date(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : null;
}

function parse_damage_time(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
            return sprintf('%02d:%02d:00', $h, $i);
        }
    }
    return null;
}

function parse_vehicle_location(?string $raw): ?string
{
    $raw = trim((string) $raw);
    return isset(vehicle_location_labels()[$raw]) ? $raw : null;
}

function format_damage_date(?string $value): string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d.m.Y', $ts) : '—';
}

function format_damage_time(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    if (preg_match('/^(\d{2}):(\d{2})/', $value, $m)) {
        return $m[1] . ':' . $m[2];
    }
    return '—';
}

function damage_time_input_value(?string $value): string
{
    $fmt = format_damage_time($value);
    return $fmt === '—' ? '' : $fmt;
}

function format_vehicle_location(?string $value): string
{
    return vehicle_location_labels()[$value ?? ''] ?? '—';
}

function parse_odometer_km(mixed $raw): ?int
{
    $raw = trim(str_replace(['.', ',', ' ', 'km', 'KM'], '', (string) $raw));
    if ($raw === '' || !ctype_digit($raw)) {
        return null;
    }
    $n = (int) $raw;
    if ($n < 0 || $n > 9999999) {
        return null;
    }
    return $n;
}

function format_odometer_km(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $n = (int) $value;
    if ($n <= 0) {
        return '—';
    }
    return number_format($n, 0, ',', '.') . ' km';
}

function form_plain(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '—') {
        return '';
    }
    return $value;
}

function form_date_dmy(?string $value): string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('d.m.Y', $ts) : '';
}

function form_km_plain(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $n = (int) $value;
    return $n > 0 ? (string) $n : '';
}

function intake_form_uploaded_categories(int $fileId): array
{
    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT category FROM file_documents WHERE damage_file_id = ?'
        );
        $stmt->execute([$fileId]);
        $codes = [];
        foreach ($stmt->fetchAll() as $row) {
            $code = (string) ($row['category'] ?? '');
            if ($code !== '') {
                $codes[$code] = true;
            }
        }
        return $codes;
    } catch (Throwable $e) {
        return [];
    }
}

function cover_form_sections(): array
{
    return [
        'header_left'  => 'Üst sol (bilgi satırları)',
        'header_right' => 'Üst sağ (bilgi satırları)',
        'checks_left'  => 'Sol evrak kutuları',
        'damage'       => 'Hasar bilgileri',
        'checks_right' => 'Sağ onay kutuları',
        'footer'       => 'Alt bilgi / notlar',
    ];
}

function cover_form_kinds(): array
{
    return [
        'text'  => 'Yazı alanı',
        'check' => 'Onay kutusu',
        'notes' => 'Not kutusu',
    ];
}

function cover_form_data_sources(): array
{
    return [
        ''                    => '— Doldurulmaz (elle yazılır)',
        'customer_name'       => 'Sigortalı / müşteri adı',
        'plate'               => 'Plaka',
        'created_at'          => 'Geliş / dosya açılış tarihi',
        'insurance_company'   => 'Sigorta şirketi',
        'policy_no'           => 'Poliçe no',
        'file_number'         => 'Dosya no',
        'claim_no'            => 'Hasar no',
        'work_order_no'       => 'İş emri no',
        'customer_phone'      => 'Telefon',
        'tc_vkn'              => 'TC / VKN',
        'customer_email'      => 'E-posta',
        'odometer_km'         => 'KM',
        'damage_date'         => 'Hasar tarihi',
        'damage_time'         => 'Hasar saati',
        'damage_type'         => 'Hasar şekli',
        'damage_place'        => 'Hasar yeri',
        'note'                => 'Dosya notu',
        'advisor_name'        => 'Danışman adı',
        'vehicle_musteride'   => 'Araç müşterideyse işaretle',
        'vehicle_serviste'    => 'Araç servisteyse işaretle',
    ];
}

function cover_form_fields(bool $activeOnly = true): array
{
    $where = $activeOnly ? ' WHERE is_active = 1' : '';
    try {
        return db()->query(
            "SELECT * FROM cover_form_fields{$where}
             ORDER BY FIELD(section,'header_left','header_right','checks_left','damage','checks_right','footer'), sort_order, id"
        )->fetchAll();
    } catch (Throwable $e) {
        try {
            return db()->query(
                "SELECT * FROM cover_form_fields{$where} ORDER BY sort_order, id"
            )->fetchAll();
        } catch (Throwable $e2) {
            return [];
        }
    }
}

function cover_form_grouped(bool $activeOnly = true): array
{
    $grouped = [];
    foreach (array_keys(cover_form_sections()) as $section) {
        $grouped[$section] = [];
    }
    foreach (cover_form_fields($activeOnly) as $row) {
        $section = (string) ($row['section'] ?? '');
        if (!isset($grouped[$section])) {
            $grouped[$section] = [];
        }
        $grouped[$section][] = $row;
    }
    return $grouped;
}

function html_cover_form_check_options(?string $selected): string
{
    $selected = (string) $selected;
    $html = '<option value="">— Bağlı değil —</option>';
    $groups = cover_form_check_optgroups(false);
    foreach ($groups as $group => $options) {
        $html .= '<optgroup label="' . e((string) $group) . '">';
        foreach ($options as $code => $label) {
            $sel = $selected === (string) $code ? ' selected' : '';
            $html .= '<option value="' . e((string) $code) . '"' . $sel . '>' . e((string) $label) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

function html_category_map_options(array $selectedCodes): string
{
    $selected = [];
    foreach ($selectedCodes as $code) {
        $selected[(string) $code] = true;
    }
    $html = '';
    foreach (all_category_options() as $code => $label) {
        $sel = isset($selected[$code]) ? ' selected' : '';
        $html .= '<option value="' . e((string) $code) . '"' . $sel . '>' . e((string) $label) . '</option>';
    }
    return $html;
}

function cover_form_check_options(bool $activeOnly = true): array
{
    $out = [];
    foreach (cover_form_check_optgroups($activeOnly) as $options) {
        foreach ($options as $code => $label) {
            $out[$code] = $label;
        }
    }
    return $out;
}

function cover_form_check_optgroups(bool $activeOnly = true): array
{
    $sections = cover_form_sections();
    $groups = [];
    foreach (cover_form_fields($activeOnly) as $row) {
        if (($row['kind'] ?? '') !== 'check') {
            continue;
        }
        $sec = (string) ($row['section'] ?? '');
        $title = $sections[$sec] ?? ($sec !== '' ? $sec : 'Diğer');
        $groups[$title][(string) $row['code']] = (string) $row['label'];
    }
    return $groups;
}

function all_category_options(): array
{
    try {
        $rows = db()->query(
            'SELECT code, label, is_active FROM app_categories ORDER BY sort_order, id'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $label = (string) $row['label'];
            if (empty($row['is_active'])) {
                $label .= ' (pasif)';
            }
            $out[(string) $row['code']] = $label;
        }
        return $out;
    } catch (Throwable $e) {
        return category_labels();
    }
}

function posted_category_codes(): array
{
    $raw = $_POST['category_codes'] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' ? [$raw] : [];
    }
    $valid = all_category_options();
    $codes = [];
    foreach ($raw as $code) {
        $code = trim((string) $code);
        if ($code !== '' && isset($valid[$code])) {
            $codes[] = $code;
        }
    }
    return $codes;
}

function set_form_field_categories(PDO $pdo, string $formFieldCode, array $categoryCodes): void
{
    if (!schema_column_exists($pdo, 'app_categories', 'form_field_code')) {
        return;
    }
    $pdo->prepare(
        'UPDATE app_categories SET form_field_code = NULL WHERE form_field_code = ?'
    )->execute([$formFieldCode]);
    $upd = $pdo->prepare('UPDATE app_categories SET form_field_code = ? WHERE code = ?');
    foreach ($categoryCodes as $code) {
        $code = trim((string) $code);
        if ($code === '') {
            continue;
        }
        $upd->execute([$formFieldCode, $code]);
    }
}

function cover_form_category_map(): array
{
    try {
        $rows = db()->query(
            "SELECT code, form_field_code FROM app_categories
             WHERE form_field_code IS NOT NULL AND form_field_code != ''"
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $field = (string) ($row['form_field_code'] ?? '');
        $code = (string) ($row['code'] ?? '');
        if ($field === '' || $code === '') {
            continue;
        }
        $map[$field][] = $code;
    }
    return $map;
}

function cover_form_resolve_value(array $file, string $dataKey): string
{
    return match ($dataKey) {
        'customer_name'     => form_plain($file['customer_name'] ?? null),
        'plate'             => form_plain($file['plate'] ?? null),
        'created_at'        => form_date_dmy($file['created_at'] ?? null),
        'insurance_company' => form_plain($file['insurance_company'] ?? null),
        'policy_no'         => form_plain($file['policy_no'] ?? null),
        'file_number'       => form_plain($file['file_number'] ?? null),
        'claim_no'          => form_plain($file['claim_no'] ?? null),
        'work_order_no'     => form_plain($file['work_order_no'] ?? null),
        'customer_phone'    => form_plain($file['customer_phone'] ?? null),
        'tc_vkn'            => form_plain($file['tc_vkn'] ?? null),
        'customer_email'    => form_plain($file['customer_email'] ?? null),
        'odometer_km'       => form_km_plain($file['odometer_km'] ?? null),
        'damage_date'       => form_date_dmy($file['damage_date'] ?? null),
        'damage_time'       => form_plain(format_damage_time($file['damage_time'] ?? null) === '—'
            ? ''
            : format_damage_time($file['damage_time'] ?? null)),
        'damage_type'       => form_plain($file['damage_type'] ?? null),
        'damage_place'      => form_plain($file['damage_place'] ?? null),
        'note'              => form_plain($file['note'] ?? null),
        'advisor_name'      => form_plain($file['advisor_name'] ?? null),
        default             => '',
    };
}

function cover_form_is_checked(array $field, array $file, array $uploaded, array $catsByField): bool
{
    $dataKey = (string) ($field['data_key'] ?? '');
    if ($dataKey === 'vehicle_musteride' && ($file['vehicle_location'] ?? '') === 'musteride') {
        return true;
    }
    if ($dataKey === 'vehicle_serviste' && ($file['vehicle_location'] ?? '') === 'serviste') {
        return true;
    }
    $fieldCode = (string) ($field['code'] ?? '');
    foreach ($catsByField[$fieldCode] ?? [] as $cat) {
        if (isset($uploaded[$cat])) {
            return true;
        }
    }
    return false;
}

function unique_insurance_doc_type(PDO $pdo, int $companyId, string $title, ?int $excludeId = null): string
{
    $base = slugify_code($title);
    if ($base === '' || preg_match('/^item_/', $base)) {
        $base = 'form';
    }
    $code = $base;
    $n = 2;
    while (true) {
        $sql = 'SELECT id FROM insurance_doc_templates WHERE insurance_company_id = ? AND doc_type = ?';
        $params = [$companyId, $code];
        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $code;
        }
        $code = $base . '_' . $n;
        $n++;
        if ($n > 50) {
            return $base . '_' . bin2hex(random_bytes(2));
        }
    }
}

function company_template_doc_types(int $companyId): array
{
    $types = [];
    foreach (insurance_templates_for_company($companyId) as $tpl) {
        $types[$tpl['doc_type']] = $tpl['title'] ?: $tpl['doc_type'];
    }
    return $types;
}

function document_category_label(string $code): string
{
    $labels = category_labels();
    if (isset($labels[$code])) {
        return $labels[$code];
    }
    try {
        $stmt = db()->prepare(
            'SELECT title FROM insurance_doc_templates WHERE doc_type = ? AND is_active = 1 ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$code]);
        $title = $stmt->fetchColumn();
        if ($title) {
            return (string) $title;
        }
    } catch (Throwable $e) {
    }
    $suggested = insurance_form_doc_types();
    return $suggested[$code] ?? $code;
}

function find_insurance_company_by_name(?string $name): ?array
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }
    try {
        $stmt = db()->prepare(
            'SELECT * FROM insurance_companies
             WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
             LIMIT 1'
        );
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function find_insurance_company_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM insurance_companies WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function insurance_templates_for_company(int $companyId, bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM insurance_doc_templates WHERE insurance_company_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY title, id';
        $stmt = db()->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function find_insurance_template(int $templateId): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM insurance_doc_templates WHERE id = ? LIMIT 1');
        $stmt->execute([$templateId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function template_storage_dir(int $companyId): string
{
    $dir = rtrim((string) app_config()['paths']['uploads'], '/\\') . '/templates/' . $companyId;
    ensure_upload_guards($dir);
    return $dir;
}

/** Yalnızca görseller (kamera / galeri). */
function upload_accept_images(): string
{
    return implode(',', [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif',
        '.jpg', '.jpeg', '.png', '.webp', '.heic', '.heif',
    ]);
}

/** PDF / Word / Excel (fotoğafsız belge). */
function upload_accept_office(): string
{
    return implode(',', [
        'application/pdf', '.pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.doc', '.docx',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.xls', '.xlsx',
    ]);
}

/** Evrak yükleme accept attribute (görsel + PDF/Word/Excel). */
function upload_accept_documents(): string
{
    return upload_accept_images() . ',' . upload_accept_office();
}

function document_type_badge(?string $mime, string $originalName): string
{
    $mime = strtolower((string) $mime);
    if (str_starts_with($mime, 'image/')) {
        return '';
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($mime === 'application/pdf' || $ext === 'pdf') {
        return 'PDF';
    }
    if (in_array($ext, ['doc', 'docx'], true) || str_contains($mime, 'word')) {
        return 'WORD';
    }
    if (in_array($ext, ['xls', 'xlsx'], true) || str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) {
        return 'EXCEL';
    }
    return strtoupper($ext !== '' ? $ext : 'DOSYA');
}

/** Tarayıcıda açılabilen evrak mı (PDF / görsel)? */
function document_is_browser_viewable(?string $mime, string $originalName): bool
{
    $badge = document_type_badge($mime, $originalName);
    return $badge === '' || $badge === 'PDF';
}

function document_view_url(int $docId, bool $download = false): string
{
    $url = '/api/view_doc.php?id=' . $docId;
    if ($download) {
        $url .= '&download=1';
    }
    return $url;
}

function validate_document_mime(string $tmpPath, string $originalName): ?array
{
    $image = validate_upload_mime($tmpPath, $originalName);
    if ($image) {
        return $image;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) (finfo_file($finfo, $tmpPath) ?: ''));
    finfo_close($finfo);

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $head = @file_get_contents($tmpPath, false, null, 0, 8) ?: '';
    $isPdf = str_starts_with($head, '%PDF') || $mime === 'application/pdf';
    $isOle = strlen($head) >= 4 && $head[0] === "\xD0" && $head[1] === "\xCF" && $head[2] === "\x11" && $head[3] === "\xE0";
    $isZip = strlen($head) >= 2 && $head[0] === 'P' && $head[1] === 'K';

    if ($ext === 'pdf' && ($isPdf || $mime === 'application/octet-stream' || $mime === '')) {
        return ['mime' => 'application/pdf', 'ext' => 'pdf'];
    }
    if ($isPdf && ($ext === 'pdf' || $ext === '')) {
        return ['mime' => 'application/pdf', 'ext' => 'pdf'];
    }

    // Word
    if ($ext === 'docx' && ($isZip || str_contains($mime, 'wordprocessingml') || $mime === 'application/zip' || $mime === 'application/octet-stream')) {
        return ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'ext' => 'docx'];
    }
    if ($ext === 'doc' && ($isOle || $mime === 'application/msword' || $mime === 'application/octet-stream' || str_contains($mime, 'msword'))) {
        return ['mime' => 'application/msword', 'ext' => 'doc'];
    }
    if (str_contains($mime, 'wordprocessingml')) {
        return ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'ext' => $ext === 'doc' ? 'doc' : 'docx'];
    }
    if ($mime === 'application/msword') {
        return ['mime' => 'application/msword', 'ext' => $ext === 'docx' ? 'docx' : 'doc'];
    }

    // Excel
    if ($ext === 'xlsx' && ($isZip || str_contains($mime, 'spreadsheetml') || $mime === 'application/zip' || $mime === 'application/octet-stream')) {
        return ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => 'xlsx'];
    }
    if ($ext === 'xls' && ($isOle || str_contains($mime, 'ms-excel') || $mime === 'application/vnd.ms-excel' || $mime === 'application/octet-stream')) {
        return ['mime' => 'application/vnd.ms-excel', 'ext' => 'xls'];
    }
    if (str_contains($mime, 'spreadsheetml')) {
        return ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => $ext === 'xls' ? 'xls' : 'xlsx'];
    }
    if ($mime === 'application/vnd.ms-excel' || str_contains($mime, 'ms-excel')) {
        return ['mime' => 'application/vnd.ms-excel', 'ext' => $ext === 'xlsx' ? 'xlsx' : 'xls'];
    }

    return null;
}

function slugify_code(string $label): string
{
    $map = [
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
    ];
    $s = strtr($label, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
    $s = trim($s, '_');
    return $s !== '' ? $s : 'item_' . bin2hex(random_bytes(3));
}

function safe_zip_name(string $name): string
{
    $name = preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/', '-', $name) ?? 'dosya';
    return trim($name) !== '' ? $name : 'dosya';
}

function generate_file_number(PDO $pdo): string
{
    $year = (int) date('y');
    $stmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM damage_files');
    $nextId = (int) $stmt->fetchColumn();
    return sprintf('HD-%02d-%04d', $year, $nextId);
}

function add_file_log(PDO $pdo, int $fileId, int $userId, string $description): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO file_logs (damage_file_id, user_id, action_description) VALUES (?, ?, ?)'
    );
    $stmt->execute([$fileId, $userId, mb_substr($description, 0, 500)]);
}

function ensure_upload_guards(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\nphp_flag engine off\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8)$\">\n    Deny from all\n</FilesMatch>\n");
    }
    $index = $dir . '/index.html';
    if (!file_exists($index)) {
        file_put_contents($index, '');
    }
}

function random_filename(string $ext): string
{
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

function normalize_uploaded_files(array $field): array
{
    if (!isset($field['name']) || $field['name'] === '') {
        return [];
    }

    if (!is_array($field['name'])) {
        return [[
            'name'     => (string) $field['name'],
            'type'     => (string) ($field['type'] ?? ''),
            'tmp_name' => (string) ($field['tmp_name'] ?? ''),
            'error'    => (int) ($field['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($field['size'] ?? 0),
        ]];
    }

    $items = [];
    foreach ($field['name'] as $i => $name) {
        if ($name === '' || $name === null) {
            continue;
        }
        $items[] = [
            'name'     => (string) $name,
            'type'     => (string) ($field['type'][$i] ?? ''),
            'tmp_name' => (string) ($field['tmp_name'][$i] ?? ''),
            'error'    => (int) ($field['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($field['size'][$i] ?? 0),
        ];
    }

    return $items;
}

function validate_upload_mime(string $tmpPath, string $originalName): ?array
{
    $allowed = [
        'image/jpeg'  => 'jpg',
        'image/jpg'   => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'   => 'png',
        'image/webp'  => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (in_array($mime, ['image/heic', 'image/heif'], true)) {
        return null;
    }

    if (!isset($allowed[$mime])) {
        $info = @getimagesize($tmpPath);
        if (is_array($info) && !empty($info[2])) {
            $map = [
                IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
                IMAGETYPE_PNG  => ['mime' => 'image/png', 'ext' => 'png'],
                IMAGETYPE_WEBP => ['mime' => 'image/webp', 'ext' => 'webp'],
            ];
            if (isset($map[$info[2]])) {
                return $map[$info[2]];
            }
        }
        return null;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    if ($ext === '' || !in_array($ext, ['jpg', 'png', 'webp'], true)) {
        $ext = $allowed[$mime];
    }

    if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)) {
        $storedMime = 'image/jpeg';
    } else {
        $storedMime = $mime;
    }

    return ['mime' => $storedMime, 'ext' => $ext];
}

function upload_validation_error(string $tmpPath, string $originalName): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (in_array($mime, ['image/heic', 'image/heif'], true)) {
        return ($originalName !== '' ? $originalName . ': ' : '')
            . 'HEIC formatı desteklenmiyor. Galeriden JPEG/PNG seçin veya kamerayı JPEG moduna alın.';
    }

    return ($originalName !== '' ? $originalName . ': ' : '')
        . 'Geçersiz dosya türü (JPEG, PNG, WebP, PDF, Word, Excel)';
}

function upload_error_message(int $code, string $name): string
{
    $label = $name !== '' ? $name . ': ' : '';
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . 'Dosya boyutu limiti aşıldı (max 20MB)',
        UPLOAD_ERR_PARTIAL => $label . 'Yükleme yarım kaldı, tekrar deneyin',
        UPLOAD_ERR_NO_FILE => $label . 'Dosya seçilmedi',
        default => $label . 'Yükleme hatası (kod ' . $code . ')',
    };
}

function is_workshop_upload_granted(array $file): bool
{
    $until = $file['workshop_upload_until'] ?? null;
    if (!$until) {
        return false;
    }
    $ts = strtotime((string) $until);
    return $ts !== false && $ts > time();
}

function can_grant_workshop_upload(array $user, array $file): bool
{
    if (user_can($user, 'hasar_edit_all')) {
        return true;
    }
    return user_can($user, 'hasar_edit_own') && (int) $file['advisor_id'] === (int) $user['id'];
}

function upload_remaining_label(?string $until): ?string
{
    if (!$until) {
        return null;
    }
    $ts = strtotime($until);
    if ($ts === false || $ts <= time()) {
        return null;
    }
    $sec = $ts - time();
    $hours = (int) floor($sec / 3600);
    $mins = (int) floor(($sec % 3600) / 60);
    if ($hours >= 24) {
        $days = (int) floor($hours / 24);
        $remH = $hours % 24;
        return $days . 'g ' . $remH . 's kaldı';
    }
    if ($hours > 0) {
        return $hours . 's ' . $mins . 'dk kaldı';
    }
    return $mins . 'dk kaldı';
}

function workshop_upload_remaining_label(array $file): ?string
{
    if (!is_workshop_upload_granted($file)) {
        return null;
    }
    return upload_remaining_label((string) $file['workshop_upload_until']);
}

function is_customer_upload_granted(array $file): bool
{
    $until = $file['customer_upload_until'] ?? null;
    if (!$until) {
        return false;
    }
    $ts = strtotime((string) $until);
    return $ts !== false && $ts > time();
}

function can_grant_customer_upload(array $user, array $file): bool
{
    return can_grant_workshop_upload($user, $file);
}

function customer_upload_remaining_label(array $file): ?string
{
    if (!is_customer_upload_granted($file)) {
        return null;
    }
    return upload_remaining_label((string) $file['customer_upload_until']);
}

function customer_upload_categories(): array
{
    $all = category_labels();
    unset($all['onarim']);
    return $all;
}

function app_base_url(): string
{
    return rtrim((string) (app_config()['app']['url'] ?? ''), '/');
}

function customer_portal_url(?string $plate = null, ?string $token = null): string
{
    $base = app_base_url() . '/musteri/';
    if ($token) {
        return $base . '?t=' . rawurlencode($token);
    }
    if ($plate) {
        return $base . '?plaka=' . rawurlencode(format_plate($plate));
    }
    return $base;
}

function get_file_permissions(array $user, array $file): array
{
    $status = $file['status'];
    $isOwner = (int) $file['advisor_id'] === (int) $user['id'];
    $customerGrantActive = is_customer_upload_granted($file);

    $canEditAll = user_can($user, 'hasar_edit_all');
    $canEditOwn = user_can($user, 'hasar_edit_own') && $isOwner;
    $canEdit = $canEditAll || $canEditOwn;
    $canUploadAll = $canEdit;
    $canUploadOnarim = user_can($user, 'hasar_status_limited') && $status === 'onarimda' && !$canEdit;
    $canUpload = $canUploadAll || $canUploadOnarim;

    $allowedCategories = [];
    if ($canUploadAll) {
        $allowedCategories = array_keys(category_labels());
    } elseif ($canUploadOnarim) {
        $allowedCategories = array_key_exists('onarim', category_labels())
            ? ['onarim']
            : array_keys(category_labels());
    }

    $allowedStatuses = [];
    if (user_can($user, 'hasar_status_all') && ($canEditAll || $canEditOwn)) {
        $allowedStatuses = array_keys(status_labels());
    } elseif (user_can($user, 'hasar_status_limited')) {
        if ($status === 'onarimda') {
            $allowedStatuses = ['onarimda', 'teslime_hazir'];
        } elseif ($status === 'teslime_hazir') {
            $allowedStatuses = ['teslime_hazir', 'onarimda'];
        }
    }

    return [
        'can_view'                   => user_can($user, 'access_hasar'),
        'can_edit'                   => $canEdit,
        'can_upload'                 => $canUpload,
        'can_delete_docs'            => $canEdit,
        'can_change_status'          => !empty($allowedStatuses),
        'allowed_categories'         => $allowedCategories,
        'allowed_statuses'           => $allowedStatuses,
        'can_grant_workshop_upload'  => false,
        'workshop_upload_active'     => false,
        'can_grant_customer_upload'  => can_grant_customer_upload($user, $file),
        'customer_upload_active'     => $customerGrantActive,
        'customer_upload_until'      => $file['customer_upload_until'] ?? null,
        'customer_upload_hours'      => isset($file['customer_upload_hours']) ? (int) $file['customer_upload_hours'] : null,
        'customer_upload_remaining'  => customer_upload_remaining_label($file),
        'customer_upload_note'       => $file['customer_upload_note'] ?? null,
        'customer_upload_token'      => $file['customer_upload_token'] ?? null,
    ];
}

function can_change_status(array $user, array $file, string $newStatus): bool
{
    $perms = get_file_permissions($user, $file);
    return in_array($newStatus, $perms['allowed_statuses'], true);
}

function can_upload_category(array $user, array $file, string $category): bool
{
    $perms = get_file_permissions($user, $file);
    return $perms['can_upload'] && in_array($category, $perms['allowed_categories'], true);
}

function can_access_file(array $user, array $file): bool
{
    return user_can($user, 'access_hasar');
}

function format_plate(string $plate): string
{
    return normalize_plate($plate);
}

function normalize_plate(string $plate): string
{
    $p = strtoupper(trim($plate));
    return preg_replace('/[^A-Z0-9]/', '', $p) ?? '';
}

function format_plate_display(string $plate): string
{
    $p = normalize_plate($plate);
    if (preg_match('/^(\d{2})([A-Z]{1,3})(\d{2,4})$/', $p, $m)) {
        return $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }
    return $p;
}

function is_valid_plate(string $plate): bool
{
    return (bool) preg_match('/^\d{2}[A-Z]{1,3}\d{2,4}$/', normalize_plate($plate));
}

function plate_badge_html(string $plate, ?string $workOrderNo = null): string
{
    $display = e(format_plate_display($plate));
    $html = '<span class="plate-badge"><span class="plate-tr">TR</span><span class="plate-num">' . $display . '</span></span>';
    if ($workOrderNo !== null && trim($workOrderNo) !== '') {
        $html .= ' <span class="work-order-badge" title="İş emri no">' . e(trim($workOrderNo)) . '</span>';
    }
    return $html;
}

function app_setting(string $key, ?string $default = null): ?string
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function app_setting_set(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function wa_default_status_template(): string
{
    return "Merhaba {name},\n\n{plate} plakalı aracınız {status_text}\nDosya no: {file_number}\n{work_order_line}\nOTOHASAR";
}

function wa_default_customer_docs_template(): string
{
    return "Merhaba {name},\n\n{plate} plakalı aracınız (dosya {file_number}) için eksik evrak yüklemeniz gerekiyor.{note_line}\n\nDurumu görmek ve fotoğraf yüklemek için:\n{portal_url}\n\nYükleme izni: {hours_label}\n\nOTOHASAR";
}

function wa_render_template(string $template, array $vars): string
{
    $out = $template;
    foreach ($vars as $k => $v) {
        $out = str_replace('{' . $k . '}', (string) $v, $out);
    }
    return trim(preg_replace("/\n{3,}/", "\n\n", $out) ?? $out);
}

function wa_status_message(string $customerName, string $plate, string $fileNumber, string $status, ?string $workOrderNo = null): string
{
    $name = trim($customerName) !== '' ? trim($customerName) : 'Değerli müşterimiz';
    $lines = [
        'evrak_bekliyor' => 'evrak bekleniyor.',
        'eksperde'       => 'ekspertiz sürecine alınmıştır.',
        'parca_bekliyor' => 'parça bekleniyor.',
        'onarimda'       => 'onarım durumuna geçmiştir.',
        'teslime_hazir'  => 'teslime hazırdır, teslim alabilirsiniz.',
        'tamamlandi'     => 'işlemleri tamamlanmıştır. İyi günler dileriz.',
    ];
    $statusText = $lines[$status] ?? ('durumu: ' . (status_labels()[$status] ?? $status));
    $tpl = app_setting('wa_status_template', wa_default_status_template()) ?? wa_default_status_template();
    $workOrderLine = ($workOrderNo !== null && trim($workOrderNo) !== '')
        ? 'İş emri no: ' . trim($workOrderNo)
        : '';

    return wa_render_template($tpl, [
        'name'            => $name,
        'plate'           => format_plate_display($plate),
        'file_number'     => $fileNumber,
        'status'          => $status,
        'status_text'     => $statusText,
        'status_label'    => status_labels()[$status] ?? $status,
        'work_order_no'   => trim($workOrderNo ?? ''),
        'work_order_line' => $workOrderLine,
    ]);
}

function wa_customer_docs_message(
    string $customerName,
    string $plate,
    string $fileNumber,
    string $portalUrl,
    int $hours,
    ?string $note = null,
    ?string $workOrderNo = null
): string {
    $name = trim($customerName) !== '' ? trim($customerName) : 'Değerli müşterimiz';
    $hoursLabel = $hours >= 24 && $hours % 24 === 0
        ? ((int) ($hours / 24)) . ' gün'
        : $hours . ' saat';
    $noteLine = ($note !== null && trim($note) !== '')
        ? "\nEksik evrak: " . trim($note)
        : '';
    $tpl = app_setting('wa_customer_docs_template', wa_default_customer_docs_template()) ?? wa_default_customer_docs_template();

    return wa_render_template($tpl, [
        'name'          => $name,
        'plate'         => format_plate_display($plate),
        'file_number'   => $fileNumber,
        'portal_url'    => $portalUrl,
        'hours'         => (string) $hours,
        'hours_label'   => $hoursLabel,
        'note'          => trim($note ?? ''),
        'note_line'     => $noteLine,
        'work_order_no' => trim($workOrderNo ?? ''),
    ]);
}

function wa_custom_templates(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM wa_templates';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function find_wa_template(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM wa_templates WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Common placeholders for a damage file WhatsApp message. */
function wa_file_template_vars(array $file, array $extra = []): array
{
    $status = (string) ($file['status'] ?? '');
    $lines = [
        'evrak_bekliyor' => 'evrak bekleniyor.',
        'eksperde'       => 'ekspertiz sürecine alınmıştır.',
        'parca_bekliyor' => 'parça bekleniyor.',
        'onarimda'       => 'onarım durumuna geçmiştir.',
        'teslime_hazir'  => 'teslime hazırdır, teslim alabilirsiniz.',
        'tamamlandi'     => 'işlemleri tamamlanmıştır. İyi günler dileriz.',
    ];
    $name = trim((string) ($file['customer_name'] ?? ''));
    if ($name === '') {
        $name = 'Değerli müşterimiz';
    }
    $workOrderNo = trim((string) ($file['work_order_no'] ?? ''));
    $hours = (int) ($extra['hours'] ?? 48);
    $hoursLabel = $hours >= 24 && $hours % 24 === 0
        ? ((int) ($hours / 24)) . ' gün'
        : $hours . ' saat';
    $note = trim((string) ($extra['note'] ?? ($file['customer_upload_note'] ?? '')));
    $noteLine = $note !== '' ? "\nEksik evrak: " . $note : '';
    $portalUrl = (string) ($extra['portal_url'] ?? customer_portal_url($file['plate'] ?? null));

    return array_merge([
        'name'            => $name,
        'plate'           => format_plate_display((string) ($file['plate'] ?? '')),
        'file_number'     => (string) ($file['file_number'] ?? ''),
        'status'          => $status,
        'status_text'     => $lines[$status] ?? ('durumu: ' . (status_labels()[$status] ?? $status)),
        'status_label'    => status_labels()[$status] ?? $status,
        'work_order_no'   => $workOrderNo,
        'work_order_line' => $workOrderNo !== '' ? 'İş emri no: ' . $workOrderNo : '',
        'portal_url'      => $portalUrl,
        'hours'           => (string) $hours,
        'hours_label'     => $hoursLabel,
        'note'            => $note,
        'note_line'       => $noteLine,
        'insurance'       => (string) ($file['insurance_company'] ?? ''),
        'phone'           => (string) ($file['customer_phone'] ?? ''),
    ], $extra);
}

function wa_compose_message(string $body, array $file, array $extra = []): string
{
    return wa_render_template($body, wa_file_template_vars($file, $extra));
}

function wa_digits(?string $phone): ?string
{
    $d = preg_replace('/\D+/', '', $phone ?? '') ?? '';
    if ($d === '') {
        return null;
    }
    if (str_starts_with($d, '00')) {
        $d = substr($d, 2);
    }
    if (str_starts_with($d, '0')) {
        $d = '90' . substr($d, 1);
    } elseif (strlen($d) === 10 && str_starts_with($d, '5')) {
        $d = '90' . $d;
    }
    if (strlen($d) < 11 || strlen($d) > 15) {
        return null;
    }
    return $d;
}

function wa_url(?string $phone, string $text): ?string
{
    $digits = wa_digits($phone);
    if ($digits === null) {
        return null;
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function wa_button_html(?string $phone, string $customerName, string $plate, string $fileNumber, string $status, int $fileId, ?string $workOrderNo = null): string
{
    $url = wa_url($phone, wa_status_message($customerName, $plate, $fileNumber, $status, $workOrderNo));
    if ($url === null) {
        return '';
    }
    return '<a class="btn-wa" href="' . e($url) . '" target="_blank" rel="noopener" data-file-id="' . $fileId . '" data-status="' . e($status) . '" title="WhatsApp ile bildir">WhatsApp</a>';
}

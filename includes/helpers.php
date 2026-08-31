<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function role_labels(): array
{
    return [
        'admin'    => 'Yönetici (Admin)',
        'advisor'  => 'Hasar Danışmanı',
        'manager'  => 'Servis Yöneticisi',
        'workshop' => 'Atölye Personeli',
    ];
}

function is_admin_user(array $user): bool
{
    return in_array($user['role'], ['admin', 'manager'], true);
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

function validate_upload_mime(string $tmpPath, string $originalName): ?array
{
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return null;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }

    $normalizedExt = $ext === 'jpeg' ? 'jpg' : $ext;
    if ($normalizedExt !== $allowed[$mime] && !($mime === 'image/jpeg' && in_array($ext, ['jpg', 'jpeg'], true))) {
        return null;
    }

    return ['mime' => $mime, 'ext' => $normalizedExt];
}

function get_file_permissions(array $user, array $file): array
{
    $role = $user['role'];
    $status = $file['status'];
    $isOwner = (int) $file['advisor_id'] === (int) $user['id'];
    $isManager = $role === 'manager' || $role === 'admin';

    $canEdit = $isManager || ($role === 'advisor' && $isOwner);
    $canUploadAll = $canEdit;
    $canUploadOnarim = $role === 'workshop' && $status === 'onarimda';
    $canUpload = $canUploadAll || $canUploadOnarim;

    $allowedCategories = [];
        if ($canUploadAll) {
        $allowedCategories = array_keys(category_labels());
        if ($role === 'workshop') {
            // workshop only onarım when already filtered by canUploadOnarim
        }
    } elseif ($canUploadOnarim) {
        $allowedCategories = array_key_exists('onarim', category_labels())
            ? ['onarim']
            : array_keys(category_labels());
    }

    $allowedStatuses = [];
    if ($isManager || ($role === 'advisor' && $isOwner)) {
        $allowedStatuses = array_keys(status_labels());
    } elseif ($role === 'workshop') {
        if ($status === 'onarimda') {
            $allowedStatuses = ['onarimda', 'teslime_hazir'];
        } elseif ($status === 'teslime_hazir') {
            $allowedStatuses = ['teslime_hazir', 'onarimda'];
        }
    }

    return [
        'can_view'             => true,
        'can_edit'             => $canEdit,
        'can_upload'           => $canUpload,
        'can_delete_docs'      => $canEdit,
        'can_change_status'    => !empty($allowedStatuses),
        'allowed_categories'   => $allowedCategories,
        'allowed_statuses'     => $allowedStatuses,
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
    return in_array($user['role'], ['advisor', 'manager', 'workshop'], true);
}

function format_plate(string $plate): string
{
    return strtoupper(trim($plate));
}

function plate_badge_html(string $plate): string
{
    $plate = e(format_plate($plate));
    return '<span class="plate-badge"><span class="plate-tr">TR</span><span class="plate-num">' . $plate . '</span></span>';
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

function wa_status_message(string $customerName, string $plate, string $fileNumber, string $status): string
{
    $name = trim($customerName) !== '' ? trim($customerName) : 'Değerli müşterimiz';
    $plate = format_plate($plate);
    $lines = [
        'evrak_bekliyor' => 'evrak bekleniyor.',
        'eksperde'       => 'ekspertiz sürecine alınmıştır.',
        'parca_bekliyor' => 'parça bekleniyor.',
        'onarimda'       => 'onarım durumuna geçmiştir.',
        'teslime_hazir'  => 'teslime hazırdır, teslim alabilirsiniz.',
        'tamamlandi'     => 'işlemleri tamamlanmıştır. İyi günler dileriz.',
    ];
    $mid = $lines[$status] ?? ('durumu: ' . (status_labels()[$status] ?? $status));

    return "Merhaba {$name},\n\n{$plate} plakalı aracınız {$mid}\nDosya no: {$fileNumber}\n\nOTOHASAR";
}

function wa_url(?string $phone, string $text): ?string
{
    $digits = wa_digits($phone);
    if ($digits === null) {
        return null;
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function wa_button_html(?string $phone, string $customerName, string $plate, string $fileNumber, string $status, int $fileId): string
{
    $url = wa_url($phone, wa_status_message($customerName, $plate, $fileNumber, $status));
    if ($url === null) {
        return '';
    }
    return '<a class="btn-wa" href="' . e($url) . '" target="_blank" rel="noopener" data-file-id="' . $fileId . '" data-status="' . e($status) . '" title="WhatsApp ile bildir">WhatsApp</a>';
}

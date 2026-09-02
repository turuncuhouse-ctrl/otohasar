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
        'admin'    => 'Sistem Admin',
        'manager'  => 'Servis Yöneticisi',
        'advisor'  => 'Hasar Danışmanı',
        'workshop' => 'Atölye Personeli',
    ];
}

function is_admin_user(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function is_manager_user(array $user): bool
{
    return ($user['role'] ?? '') === 'manager';
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

    return ($originalName !== '' ? $originalName . ': ' : '') . 'Geçersiz dosya türü (JPEG, PNG, WebP)';
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
    $role = $user['role'] ?? '';
    if ($role === 'manager') {
        return true;
    }
    return $role === 'advisor' && (int) $file['advisor_id'] === (int) $user['id'];
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
    $role = $user['role'];
    $status = $file['status'];
    $isManager = $role === 'manager';
    $isOwner = (int) $file['advisor_id'] === (int) $user['id'];
    $customerGrantActive = is_customer_upload_granted($file);

    $canEdit = $isManager || ($role === 'advisor' && $isOwner);
    $canUploadAll = $canEdit;
    // Atölye: onarımda iken onarım evrakı yükleyebilir
    $canUploadOnarim = $role === 'workshop' && $status === 'onarimda';
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
        'can_view'                   => true,
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
    return in_array($user['role'], ['advisor', 'manager', 'workshop', 'admin'], true);
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

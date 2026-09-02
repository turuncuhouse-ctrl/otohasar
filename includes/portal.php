<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function portal_logout(): void
{
    start_session();
    unset(
        $_SESSION['portal_plate'],
        $_SESSION['portal_file_id'],
        $_SESSION['portal_via_token'],
        $_SESSION['portal_kvkk_ok'],
        $_SESSION['portal_kvkk_version'],
        $_SESSION['portal_kvkk_at'],
        $_SESSION['portal_pending_plate'],
        $_SESSION['portal_pending_file_id'],
        $_SESSION['portal_pending_via_token']
    );
}

function portal_kvkk_version(): string
{
    return 'kvkk-v1';
}

function portal_kvkk_accepted(): bool
{
    start_session();
    return !empty($_SESSION['portal_kvkk_ok'])
        && ($_SESSION['portal_kvkk_version'] ?? '') === portal_kvkk_version();
}

function portal_require_kvkk(): void
{
    if (portal_kvkk_accepted()) {
        return;
    }
    header('Location: /musteri/kvkk.php');
    exit;
}

function portal_set_pending_access(string $plate, ?int $fileId = null, bool $viaToken = false): void
{
    start_session();
    $_SESSION['portal_pending_plate'] = format_plate($plate);
    $_SESSION['portal_pending_file_id'] = $fileId;
    $_SESSION['portal_pending_via_token'] = $viaToken;
}

function portal_pending_plate(): ?string
{
    start_session();
    $p = $_SESSION['portal_pending_plate'] ?? null;
    return is_string($p) && $p !== '' ? $p : null;
}

function portal_accept_kvkk(?string $plate = null, ?int $fileId = null): void
{
    start_session();
    $plate = format_plate($plate ?: (portal_pending_plate() ?: (portal_plate() ?: '')));
    if ($plate === '') {
        return;
    }
    $version = portal_kvkk_version();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    try {
        $stmt = db()->prepare(
            'INSERT INTO portal_kvkk_consents (plate, damage_file_id, ip, user_agent, version)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $plate,
            $fileId && $fileId > 0 ? $fileId : null,
            $ip,
            $ua,
            $version,
        ]);
    } catch (Throwable $e) {
        // Tablo yoksa bile oturum bayrağı konur; migrate sonrası kaydedilir.
    }

    $_SESSION['portal_kvkk_ok'] = true;
    $_SESSION['portal_kvkk_version'] = $version;
    $_SESSION['portal_kvkk_at'] = date('c');
}

function portal_set_plate(string $plate): void
{
    start_session();
    $_SESSION['portal_plate'] = format_plate($plate);
    unset($_SESSION['portal_via_token']);
}

function portal_set_file(int $fileId, string $plate, bool $viaToken = false): void
{
    start_session();
    $_SESSION['portal_file_id'] = $fileId;
    $_SESSION['portal_plate'] = format_plate($plate);
    $_SESSION['portal_via_token'] = $viaToken;
}

function portal_plate(): ?string
{
    start_session();
    $p = $_SESSION['portal_plate'] ?? null;
    return is_string($p) && $p !== '' ? $p : null;
}

function portal_file_id(): ?int
{
    start_session();
    $id = $_SESSION['portal_file_id'] ?? null;
    return $id ? (int) $id : null;
}

function portal_require_plate(): string
{
    $plate = portal_plate();
    if ($plate === null) {
        header('Location: /musteri/');
        exit;
    }
    return $plate;
}

function find_files_by_plate(string $plate): array
{
    $stmt = db()->prepare(
        'SELECT df.*,
                v.plate, v.brand, v.model,
                c.name AS customer_name, c.phone AS customer_phone
         FROM damage_files df
         JOIN vehicles v ON v.id = df.vehicle_id
         JOIN customers c ON c.id = v.customer_id
         WHERE REPLACE(UPPER(v.plate), " ", "") = ?
         ORDER BY (df.status = \'tamamlandi\') ASC, df.updated_at DESC'
    );
    $stmt->execute([normalize_plate($plate)]);
    return $stmt->fetchAll();
}

function find_file_by_customer_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        return null;
    }
    try {
        $stmt = db()->prepare(
            'SELECT df.*, v.plate, v.brand, v.model,
                    c.name AS customer_name, c.phone AS customer_phone
             FROM damage_files df
             JOIN vehicles v ON v.id = df.vehicle_id
             JOIN customers c ON c.id = v.customer_id
             WHERE df.customer_upload_token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function find_portal_file(int $fileId, string $plate): ?array
{
    $stmt = db()->prepare(
        'SELECT df.*, v.plate, v.brand, v.model, v.year, v.color,
                c.name AS customer_name, c.phone AS customer_phone
         FROM damage_files df
         JOIN vehicles v ON v.id = df.vehicle_id
         JOIN customers c ON c.id = v.customer_id
         WHERE df.id = ? AND REPLACE(UPPER(v.plate), " ", "") = ?'
    );
    $stmt->execute([$fileId, normalize_plate($plate)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function portal_rate_limited(): bool
{
    start_session();
    $now = time();
    $bucket = $_SESSION['portal_lookup_times'] ?? [];
    if (!is_array($bucket)) {
        $bucket = [];
    }
    $bucket = array_values(array_filter($bucket, static fn($t) => is_int($t) && ($now - $t) < 3600));
    if (count($bucket) >= 20) {
        $_SESSION['portal_lookup_times'] = $bucket;
        return true;
    }
    $bucket[] = $now;
    $_SESSION['portal_lookup_times'] = $bucket;
    return false;
}

// Oturum + CSRF her portal isteğinde hazır olsun (ilk POST hatasını önler)
start_session();
if (empty($_SESSION['csrf_token'])) {
    csrf_token();
}

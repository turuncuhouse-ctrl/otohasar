<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function portal_logout(): void
{
    start_session();
    unset($_SESSION['portal_plate'], $_SESSION['portal_file_id'], $_SESSION['portal_via_token']);
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
         WHERE v.plate = ?
         ORDER BY (df.status = \'tamamlandi\') ASC, df.updated_at DESC'
    );
    $stmt->execute([format_plate($plate)]);
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
         WHERE df.id = ? AND v.plate = ?'
    );
    $stmt->execute([$fileId, format_plate($plate)]);
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

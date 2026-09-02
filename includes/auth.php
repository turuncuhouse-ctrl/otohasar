<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_session();
    return $token !== null && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

function authenticate_user(): ?array
{
    start_session();

    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, name, username, role, email, phone, is_active FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    $token = get_bearer_token();
    if ($token) {
        $hash = hash('sha256', $token);
        $stmt = db()->prepare(
            'SELECT u.id, u.name, u.username, u.role, u.email, u.phone, u.is_active
             FROM auth_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > NOW() AND u.is_active = 1'
        );
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    }

    return null;
}

function require_auth(): array
{
    $user = authenticate_user();
    if (!$user) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_error('Oturum gerekli', 401);
        }
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function require_api_auth(): array
{
    $user = authenticate_user();
    if (!$user) {
        json_error('Oturum gerekli', 401);
    }
    return $user;
}

function verify_api_csrf(): void
{
    if (get_bearer_token()) {
        return;
    }
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf($token)) {
        json_error('CSRF doğrulaması başarısız — sayfayı yenileyip tekrar deneyin', 403);
    }
}

function login_user(array $user): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    csrf_token();
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function create_auth_token(int $userId): string
{
    $config = app_config();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + $config['app']['token_ttl']);

    $stmt = db()->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $hash, $expires]);

    return $token;
}

function verify_login(string $username, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $hash = $user['password'] ?? $dummyHash;

    if (!password_verify($password, $hash) || !$user) {
        password_verify($password, $dummyHash);
        return null;
    }

    unset($user['password']);
    return $user;
}

function require_role(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_error('Yetkisiz erişim', 403);
        }
        http_response_code(403);
        echo 'Yetkisiz erişim';
        exit;
    }
}

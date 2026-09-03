<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$login = trim((string) ($input['username'] ?? $input['phone'] ?? $input['login'] ?? ''));
$password = $input['password'] ?? '';

if ($login === '' || $password === '') {
    json_error('Kullanıcı adı/telefon ve şifre gerekli');
}

$user = verify_login($login, $password);
if (!$user) {
    json_error('Geçersiz kullanıcı adı/telefon veya şifre', 401);
}

login_user($user);
$token = create_auth_token((int) $user['id']);

json_response([
    'ok'    => true,
    'user'  => $user,
    'token' => $token,
]);

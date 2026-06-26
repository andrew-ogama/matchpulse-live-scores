<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');

$data = read_json_body();
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['ok' => false, 'error' => 'Email and password are required.'], 422);
}

$statement = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
$statement->execute([$email]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['ok' => false, 'error' => 'Invalid login details.'], 401);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];

$update = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
$update->execute([$user['id']]);

json_response([
    'ok' => true,
    'user' => public_user($user),
]);

<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');

$data = read_json_body();
$setupKey = (string)(app_config('setup_key') ?? '');
$providedKey = (string)($data['setupKey'] ?? $data['setup_key'] ?? '');

if ($setupKey === '' || $setupKey === 'change_this_to_a_long_random_secret_before_creating_admin') {
    json_response([
        'ok' => false,
        'error' => 'Admin setup is disabled until api/config.php has a private setup_key.',
    ], 403);
}

if (!hash_equals($setupKey, $providedKey)) {
    json_response(['ok' => false, 'error' => 'Invalid setup key.'], 403);
}

$pdo = db();
$count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0 && !current_user()) {
    json_response(['ok' => false, 'error' => 'Login required to add another admin.'], 401);
}

$name = trim((string)($data['name'] ?? 'Admin'));
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if ($email === '' || strlen($password) < 10) {
    json_response(['ok' => false, 'error' => 'Email and a password of at least 10 characters are required.'], 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $statement = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())'
    );
    $statement->execute([$name ?: 'Admin', $email, $hash, 'admin']);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => 'Could not create admin. The email may already exist.'], 422);
}

json_response([
    'ok' => true,
    'message' => 'Admin user created. Remove or change setup_key after setup.',
]);

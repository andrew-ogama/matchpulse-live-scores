<?php

declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile)
    ? require $configFile
    : require __DIR__ . '/config.example.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = $config['cors_origins'] ?? [];
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session_name'] ?? 'matchpulse_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function app_config(?string $key = null)
{
    global $config;
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? null;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }
    return $decoded;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_config('db');
    $charset = $db['charset'] ?? 'utf8mb4';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['database'], $charset);

    try {
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $error) {
        json_response([
            'ok' => false,
            'error' => 'Database connection failed.',
            'hint' => 'Copy api/config.example.php to api/config.php and set your MySQL details.',
        ], 500);
    }

    return $pdo;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT id, name, email, role, active, created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user || (int)$user['active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Login required.'], 401);
    }
    return $user;
}

function public_user(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function sanitize_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    $slug = trim($slug, '-');
    return substr($slug ?: 'post', 0, 90);
}

function normalize_status(string $status): string
{
    $allowed = ['Draft', 'Ready for Review', 'Published'];
    return in_array($status, $allowed, true) ? $status : 'Draft';
}

function article_payload(array $row): array
{
    return [
        'id' => (string)$row['id'],
        'title' => $row['title'],
        'slug' => $row['slug'],
        'summary' => $row['summary'] ?? '',
        'body' => $row['body'] ?? '',
        'category' => $row['category'] ?? 'Match Report',
        'status' => $row['status'] ?? 'Draft',
        'tags' => $row['tags'] ?? '',
        'featuredImage' => $row['featured_image'] ?? '',
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
        'publishedAt' => $row['published_at'],
    ];
}

function ensure_unique_slug(PDO $pdo, string $slug, ?int $ignoreId = null): string
{
    $base = sanitize_slug($slug);
    $candidate = $base;
    $suffix = 2;

    while (true) {
        $sql = 'SELECT id FROM articles WHERE slug = ?';
        $params = [$candidate];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        if (!$statement->fetch()) {
            return $candidate;
        }
        $candidate = substr($base, 0, 82) . '-' . $suffix;
        $suffix += 1;
    }
}

<?php

require __DIR__ . '/bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $user = current_user();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $rawSlug = trim((string)($_GET['slug'] ?? ''));
    $slug = $rawSlug !== '' ? sanitize_slug($rawSlug) : '';

    if ($id > 0 || $slug !== '') {
        $where = $id > 0 ? 'id = ?' : 'slug = ?';
        $params = [$id > 0 ? $id : $slug];
        if (!$user) {
            $where .= ' AND status = ?';
            $params[] = 'Published';
        }
        $statement = $pdo->prepare("SELECT * FROM articles WHERE {$where} LIMIT 1");
        $statement->execute($params);
        $article = $statement->fetch();
        if (!$article) {
            json_response(['ok' => false, 'error' => 'Article not found.'], 404);
        }
        json_response(['ok' => true, 'article' => article_payload($article)]);
    }

    $params = [];
    $where = '';
    if (!$user) {
        $where = 'WHERE status = ?';
        $params[] = 'Published';
    } elseif (!empty($_GET['status'])) {
        $where = 'WHERE status = ?';
        $params[] = normalize_status((string)$_GET['status']);
    }

    $statement = $pdo->prepare("SELECT * FROM articles {$where} ORDER BY updated_at DESC LIMIT 50");
    $statement->execute($params);
    $articles = array_map('article_payload', $statement->fetchAll());
    json_response(['ok' => true, 'articles' => $articles]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

require_admin();
$data = read_json_body();
$action = (string)($data['action'] ?? 'save');

if ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Article id is required.'], 422);
    }
    $statement = $pdo->prepare('DELETE FROM articles WHERE id = ?');
    $statement->execute([$id]);
    json_response(['ok' => true]);
}

if ($action !== 'save') {
    json_response(['ok' => false, 'error' => 'Unknown article action.'], 422);
}

$article = $data['article'] ?? $data;
if (!is_array($article)) {
    json_response(['ok' => false, 'error' => 'Article data is required.'], 422);
}

$id = isset($article['id']) && ctype_digit((string)$article['id']) ? (int)$article['id'] : 0;
$title = trim((string)($article['title'] ?? ''));
$body = trim((string)($article['body'] ?? ''));

if ($title === '' || $body === '') {
    json_response(['ok' => false, 'error' => 'Title and body are required.'], 422);
}

$status = normalize_status((string)($article['status'] ?? 'Draft'));
$slug = ensure_unique_slug($pdo, (string)($article['slug'] ?? $title), $id ?: null);
$summary = trim((string)($article['summary'] ?? ''));
$category = trim((string)($article['category'] ?? 'Match Report')) ?: 'Match Report';
$tags = trim((string)($article['tags'] ?? ''));
$featuredImage = trim((string)($article['featuredImage'] ?? $article['featured_image'] ?? ''));
$publishedAt = $status === 'Published' ? date('Y-m-d H:i:s') : null;

if ($id > 0) {
    $existing = $pdo->prepare('SELECT id, published_at FROM articles WHERE id = ? LIMIT 1');
    $existing->execute([$id]);
    $row = $existing->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Article not found.'], 404);
    }
    $publishedAt = $status === 'Published'
        ? ($row['published_at'] ?: $publishedAt)
        : null;

    $statement = $pdo->prepare(
        'UPDATE articles SET title = ?, slug = ?, summary = ?, body = ?, category = ?, tags = ?, status = ?, featured_image = ?, published_at = ?, updated_at = NOW() WHERE id = ?'
    );
    $statement->execute([$title, $slug, $summary, $body, $category, $tags, $status, $featuredImage, $publishedAt, $id]);
} else {
    $statement = $pdo->prepare(
        'INSERT INTO articles (title, slug, summary, body, category, tags, status, featured_image, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $statement->execute([$title, $slug, $summary, $body, $category, $tags, $status, $featuredImage, $publishedAt]);
    $id = (int)$pdo->lastInsertId();
}

$statement = $pdo->prepare('SELECT * FROM articles WHERE id = ? LIMIT 1');
$statement->execute([$id]);

json_response([
    'ok' => true,
    'article' => article_payload($statement->fetch()),
]);

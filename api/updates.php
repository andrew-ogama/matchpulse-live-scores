<?php

require __DIR__ . '/bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $statement = $pdo->query('SELECT id, title, body, type, created_at FROM match_updates ORDER BY created_at DESC LIMIT 30');
    json_response(['ok' => true, 'updates' => $statement->fetchAll()]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

require_admin();
$data = read_json_body();
$action = (string)($data['action'] ?? 'save');

if ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    $statement = $pdo->prepare('DELETE FROM match_updates WHERE id = ?');
    $statement->execute([$id]);
    json_response(['ok' => true]);
}

$title = trim((string)($data['title'] ?? 'Match update'));
$body = trim((string)($data['body'] ?? $data['text'] ?? ''));
$type = trim((string)($data['type'] ?? 'info')) ?: 'info';

if ($body === '') {
    json_response(['ok' => false, 'error' => 'Update text is required.'], 422);
}

$statement = $pdo->prepare('INSERT INTO match_updates (title, body, type, created_at) VALUES (?, ?, ?, NOW())');
$statement->execute([$title, $body, $type]);

json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);

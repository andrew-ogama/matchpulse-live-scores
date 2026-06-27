<?php

require __DIR__ . '/bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$allowedKeys = ['seo', 'pages'];

if ($method === 'GET') {
    $settings = read_site_settings($pdo, $allowedKeys);
    $user = current_user();
    $pages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [];

    if (!$user) {
        $pages = array_values(array_filter($pages, static function ($page): bool {
            return is_array($page) && ($page['status'] ?? 'Draft') === 'Published';
        }));
    }

    json_response([
        'ok' => true,
        'seo' => is_array($settings['seo'] ?? null) ? $settings['seo'] : null,
        'pages' => $pages,
    ]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

require_admin();
$data = read_json_body();
$saved = [];

if (array_key_exists('seo', $data)) {
    if (!is_array($data['seo'])) {
        json_response(['ok' => false, 'error' => 'SEO settings must be an object.'], 422);
    }
    $saved['seo'] = sanitize_seo_settings($data['seo']);
}

if (array_key_exists('pages', $data)) {
    if (!is_array($data['pages'])) {
        json_response(['ok' => false, 'error' => 'Pages must be a list.'], 422);
    }
    $saved['pages'] = sanitize_pages($data['pages']);
}

if (!$saved) {
    json_response(['ok' => false, 'error' => 'No settings provided.'], 422);
}

foreach ($saved as $key => $value) {
    save_site_setting($pdo, $key, $value);
}

json_response(['ok' => true] + $saved);

function read_site_settings(PDO $pdo, array $keys): array
{
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $statement = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ({$placeholders})");
    $statement->execute($keys);

    $settings = [];
    foreach ($statement->fetchAll() as $row) {
        $decoded = json_decode((string)$row['setting_value'], true);
        $settings[$row['setting_key']] = is_array($decoded) ? $decoded : null;
    }

    return $settings;
}

function save_site_setting(PDO $pdo, string $key, array $value): void
{
    $statement = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $statement->execute([$key, json_encode($value, JSON_UNESCAPED_SLASHES)]);
}

function sanitize_seo_settings(array $seo): array
{
    $fields = [
        'focusKeyphrase',
        'title',
        'description',
        'keywords',
        'shareImage',
        'socialTitle',
        'socialDescription',
        'schemaType',
        'canonicalUrl',
        'robotsIndex',
        'robotsFollow',
        'facebook',
        'instagram',
        'x',
        'tiktok',
        'youtube',
        'whatsapp',
    ];
    $clean = [];

    foreach ($fields as $field) {
        $clean[$field] = trim((string)($seo[$field] ?? ''));
    }

    $allowedSchema = ['WebSite', 'NewsArticle', 'SportsEvent', 'Article', 'WebPage', 'Organization'];
    if (!in_array($clean['schemaType'], $allowedSchema, true)) {
        $clean['schemaType'] = 'WebSite';
    }

    $clean['robotsIndex'] = $clean['robotsIndex'] === 'noindex' ? 'noindex' : 'index';
    $clean['robotsFollow'] = $clean['robotsFollow'] === 'nofollow' ? 'nofollow' : 'follow';

    return $clean;
}

function sanitize_pages(array $pages): array
{
    $clean = [];
    $usedSlugs = [];

    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $title = trim((string)($page['title'] ?? ''));
        $body = trim((string)($page['body'] ?? ''));
        if ($title === '' || $body === '') {
            continue;
        }

        $slug = sanitize_slug((string)($page['slug'] ?? $title));
        $baseSlug = $slug;
        $suffix = 2;
        while (isset($usedSlugs[$slug])) {
            $slug = substr($baseSlug, 0, 82) . '-' . $suffix;
            $suffix += 1;
        }
        $usedSlugs[$slug] = true;

        $status = (string)($page['status'] ?? 'Draft');
        $status = $status === 'Published' ? 'Published' : 'Draft';
        $createdAt = trim((string)($page['createdAt'] ?? date('c'))) ?: date('c');

        $clean[] = [
            'id' => trim((string)($page['id'] ?? ('page-' . uniqid()))),
            'title' => substr($title, 0, 190),
            'slug' => $slug,
            'summary' => substr(trim((string)($page['summary'] ?? '')), 0, 260),
            'body' => $body,
            'status' => $status,
            'createdAt' => $createdAt,
            'updatedAt' => date('c'),
        ];
    }

    return array_slice($clean, 0, 50);
}

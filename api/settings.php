<?php

require __DIR__ . '/bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$allowedKeys = ['seo', 'pages', 'youtubeEmbeds'];

if ($method === 'GET') {
    $settings = read_site_settings($pdo, $allowedKeys);
    $user = current_user();
    $pages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [];
    $youtubeEmbeds = is_array($settings['youtubeEmbeds'] ?? null) ? $settings['youtubeEmbeds'] : [];

    if (!$user) {
        $pages = array_values(array_filter($pages, static function ($page): bool {
            return is_array($page) && ($page['status'] ?? 'Draft') === 'Published';
        }));
        $youtubeEmbeds = array_values(array_filter($youtubeEmbeds, static function ($embed): bool {
            return is_array($embed) && ($embed['status'] ?? 'Active') === 'Active';
        }));
    }

    json_response([
        'ok' => true,
        'seo' => is_array($settings['seo'] ?? null) ? $settings['seo'] : null,
        'pages' => $pages,
        'youtubeEmbeds' => $youtubeEmbeds,
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

if (array_key_exists('youtubeEmbeds', $data)) {
    if (!is_array($data['youtubeEmbeds'])) {
        json_response(['ok' => false, 'error' => 'YouTube embeds must be a list.'], 422);
    }
    $saved['youtubeEmbeds'] = sanitize_youtube_embeds($data['youtubeEmbeds']);
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

function sanitize_youtube_embeds(array $embeds): array
{
    $clean = [];

    foreach ($embeds as $embed) {
        if (!is_array($embed)) {
            continue;
        }

        $url = trim((string)($embed['url'] ?? $embed['embedUrl'] ?? ''));
        $videoId = youtube_video_id($url);

        if (!$videoId) {
            $videoId = youtube_video_id('https://www.youtube.com/watch?v=' . trim((string)($embed['videoId'] ?? '')));
        }

        if (!$videoId) {
            continue;
        }

        $title = trim((string)($embed['title'] ?? 'Official YouTube stream'));
        $matchKey = trim((string)($embed['matchKey'] ?? ''));
        $status = (string)($embed['status'] ?? 'Active');
        $createdAt = trim((string)($embed['createdAt'] ?? date('c'))) ?: date('c');

        $clean[] = [
            'id' => trim((string)($embed['id'] ?? ('youtube-' . uniqid()))),
            'title' => substr($title !== '' ? $title : 'Official YouTube stream', 0, 120),
            'matchKey' => substr($matchKey, 0, 160),
            'url' => 'https://www.youtube.com/watch?v=' . $videoId,
            'videoId' => $videoId,
            'embedUrl' => 'https://www.youtube.com/embed/' . $videoId,
            'status' => $status === 'Inactive' ? 'Inactive' : 'Active',
            'createdAt' => $createdAt,
            'updatedAt' => date('c'),
        ];
    }

    return array_slice($clean, 0, 20);
}

function youtube_video_id(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $url) ? $url : '';
    }

    $host = strtolower((string)$parts['host']);
    $host = preg_replace('/^www\./', '', $host);
    $allowedHosts = ['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'];
    if (!in_array($host, $allowedHosts, true)) {
        return '';
    }

    $path = trim((string)($parts['path'] ?? ''), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    $candidate = '';

    if ($host === 'youtu.be') {
        $candidate = $segments[0] ?? '';
    } elseif (($segments[0] ?? '') === 'watch') {
        parse_str((string)($parts['query'] ?? ''), $query);
        $candidate = (string)($query['v'] ?? '');
    } elseif (in_array(($segments[0] ?? ''), ['live', 'embed', 'shorts'], true)) {
        $candidate = $segments[1] ?? '';
    }

    return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) ? $candidate : '';
}

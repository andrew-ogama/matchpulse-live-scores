<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');
require_admin();

if (empty($_FILES['file'])) {
    json_response(['ok' => false, 'error' => 'No file uploaded.'], 422);
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Upload failed.'], 422);
}

$uploads = app_config('uploads');
$maxBytes = (int)($uploads['max_bytes'] ?? 3145728);
if ((int)$file['size'] > $maxBytes) {
    json_response(['ok' => false, 'error' => 'File is too large.'], 422);
}

$info = @getimagesize($file['tmp_name']);
if (!$info) {
    json_response(['ok' => false, 'error' => 'Only image uploads are allowed.'], 422);
}

$mime = $info['mime'] ?? '';
$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (!isset($extensions[$mime])) {
    json_response(['ok' => false, 'error' => 'Unsupported image type.'], 422);
}

$datePath = date('Y/m');
$targetDirectory = rtrim($uploads['directory'], '/\\') . '/' . $datePath;
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true)) {
    json_response(['ok' => false, 'error' => 'Could not create upload directory.'], 500);
}

$basename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
$target = $targetDirectory . '/' . $basename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    json_response(['ok' => false, 'error' => 'Could not save upload.'], 500);
}

$publicPath = rtrim($uploads['public_path'], '/') . '/' . $datePath . '/' . $basename;
$statement = db()->prepare('INSERT INTO media_uploads (file_name, file_path, mime_type, file_size, created_at) VALUES (?, ?, ?, ?, NOW())');
$statement->execute([$file['name'], $publicPath, $mime, (int)$file['size']]);

json_response([
    'ok' => true,
    'media' => [
        'id' => (int)db()->lastInsertId(),
        'url' => $publicPath,
        'name' => $file['name'],
        'mime' => $mime,
    ],
]);

<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? '';
if (strlen($password) < 10) {
    fwrite(STDERR, "Usage: php api/tools/hash-password.php \"your-strong-password\"\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;

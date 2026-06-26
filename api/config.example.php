<?php

return [
    'app_name' => 'MatchPulse',
    'session_name' => 'matchpulse_admin',
    'setup_key' => 'change_this_to_a_long_random_secret_before_creating_admin',
    'cors_origins' => [
        // Add your frontend/admin domain when the API is hosted separately.
        // Example: 'https://example.com'
    ],
    'db' => [
        'host' => 'localhost',
        'database' => 'matchpulse',
        'username' => 'matchpulse_user',
        'password' => 'change_this_password',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'directory' => __DIR__ . '/../uploads',
        'public_path' => '/uploads',
        'max_bytes' => 3 * 1024 * 1024,
    ],
];

<?php

require __DIR__ . '/bootstrap.php';

try {
    db()->query('SELECT 1');
    json_response(['ok' => true, 'status' => 'online']);
} catch (Throwable $error) {
    json_response(['ok' => false, 'status' => 'offline'], 500);
}

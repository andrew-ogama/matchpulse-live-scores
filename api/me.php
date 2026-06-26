<?php

require __DIR__ . '/bootstrap.php';

$user = current_user();

json_response([
    'ok' => true,
    'authenticated' => (bool)$user,
    'user' => $user ? public_user($user) : null,
]);

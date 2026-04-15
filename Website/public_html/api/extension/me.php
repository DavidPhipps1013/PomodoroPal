<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

$user = getAuthenticatedUser($conn);

if (!$user) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

echo json_encode([
    'user' => $user
]);
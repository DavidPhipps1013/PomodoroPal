<?php
require_once __DIR__ . '/auth.php';

$user = getAuthenticatedUser($conn);

if ($user) {
    $headers = getallheaders();
    preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches);
    $tokenHash = hash('sha256', $matches[1]);

    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
}

echo json_encode(['success' => true]);
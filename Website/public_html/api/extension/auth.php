<?php
require_once __DIR__ . '/../../app/db/database.php';

function getAuthenticatedUser($conn) {
    $authHeader = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['Authorization'])) {
        $authHeader = $_SERVER['Authorization'];
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }

    if (!$authHeader) {
        return null;
    }

    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        return null;
    }

    $token = trim($matches[1]);
    $tokenHash = hash('sha256', $token);

    $sql = "
        SELECT u.user_id, u.email, u.f_name
        FROM user_tokens t
        JOIN users u ON t.user_id = u.user_id
        WHERE t.token_hash = ?
          AND t.expires_at > NOW()
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $tokenHash);
    if (!$stmt->execute()) {
        return null;
    }

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        return null;
    }

    return $result->fetch_assoc();
}
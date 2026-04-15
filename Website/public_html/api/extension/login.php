<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../app/db/database.php';

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Missing email or password']);
    exit;
}

$sql = "SELECT user_id, f_name, email, password_hash FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "message" => "User lookup prepare failed",
        "error" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "message" => "User lookup execute failed",
        "error" => $stmt->error
    ]);
    exit;
}

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid credentials']);
    exit;
}

$user = $result->fetch_assoc();


if (!password_verify($password, $user['password_hash']) && $password !== $user['password_hash']) {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid credentials']);
    exit;
}


$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));


$insert = $conn->prepare("
    INSERT INTO user_tokens (user_id, token_hash, expires_at)
    VALUES (?, ?, ?)
");

if (!$insert) {
    http_response_code(500);
    echo json_encode([
        "message" => "Token insert prepare failed",
        "error" => $conn->error
    ]);
    exit;
}

$insert->bind_param("iss", $user['user_id'], $tokenHash, $expiresAt);

if (!$insert->execute()) {
    http_response_code(500);
    echo json_encode([
        "message" => "Token insert execute failed",
        "error" => $insert->error
    ]);
    exit;
}

echo json_encode([
    'token' => $rawToken,
    'user' => [
        'id' => $user['user_id'],
        'email' => $user['email'],
        'name' => $user['f_name']
    ]
]);
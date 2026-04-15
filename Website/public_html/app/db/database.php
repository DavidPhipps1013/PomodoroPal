<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "database";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($conn->connect_error) {
    die(json_encode([
        "error" => "DB connection failed",
        "details" => $conn->connect_error
    ]));
}
?>
<?php
$host = getenv("DB_HOST") ?: "127.0.0.1";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";
$db = getenv("DB_NAME") ?: "user_system";
$port = (int)(getenv("DB_PORT") ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]));
}

$conn->set_charset("utf8mb4");
?>

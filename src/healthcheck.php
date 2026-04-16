<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$host = getenv("DB_HOST") ?: "127.0.0.1";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";
$db = getenv("DB_NAME") ?: "user_system";
$port = (int)(getenv("DB_PORT") ?: 3306);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");

    $stmt = $conn->query("SELECT 1");
    $db_ok = $stmt !== false;

    http_response_code(200);
    echo json_encode([
        "status" => "ok",
        "app" => "PET SYSTEM API",
        "db" => $db_ok ? "up" : "down",
        "timestamp" => gmdate("c")
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "app" => "PET SYSTEM API",
        "db" => "down",
        "error" => $e->getMessage(),
        "timestamp" => gmdate("c")
    ]);
}
?>

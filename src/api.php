<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require "db.php";

// Accepts POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, "error", "Method Not Allowed. Use POST request");
}

// Get inputs
$action = $_POST['action'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

//general response function; reusable accross functions
function respond($http_code, $status, $message, $data = null) {
    http_response_code($http_code);
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

// REGISTER function
//accepts username and password, checks if username exists, if not creates new user with hashed password
if ($action === "register") {
    if (!$username || !$password)
        respond(400, "error", "Please fill all fields");

    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0)
        respond(409, "error", "Username already exists");

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users(username,password) VALUES(?,?)");
    $stmt->bind_param("ss", $username, $hash);

    $stmt->execute()
        ? respond(201, "success", "Account created successfully")
        : respond(500, "error", "Registration failed");
}

// LOGIN
// validates username and password, checks if user exists, if yes verifies password and responds accordingly
elseif ($action === "login") {
    if (!$username || !$password)
        respond(400, "error", "Please fill all fields");

    $stmt = $conn->prepare("SELECT id,password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0)
        respond(404, "error", "User not found");

    $user = $res->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        respond(200, "success", "Login successful", [
            "user_id" => $user['id'],
            "username" => $username
        ]);
    } else {
        respond(401, "error", "Incorrect password");
    }
}
<<<<<<< Updated upstream

=======
// ADD PET
// accepts user_id, pet_name and pet_type, validates them and adds new pet to database
elseif ($action === "add_pet") {
    $user_id = $_POST['user_id'] ?? '';
    $pet_name = $_POST['pet_name'] ?? '';
    $pet_type = $_POST['pet_type'] ?? '';

    if (!$user_id || !$pet_name || !$pet_type)
        respond(400, "error", "Please complete pet details");

    $stmt = $conn->prepare("INSERT INTO pets(user_id,pet_name,pet_type) VALUES(?,?,?)");
    $stmt->bind_param("iss", $user_id, $pet_name, $pet_type);

    $stmt->execute()
        ? respond(201, "success", "Pet added successfully")
        : respond(500, "error", "Failed to add pet");
}
// DELETE PET
// accepts pet id, validates it and deletes the pet from database
elseif ($action === "delete_pet") {
    $id = $_POST['id'] ?? '';

    if (!$id)
        respond(400, "error", "Select a pet to delete");

    $stmt = $conn->prepare("DELETE FROM pets WHERE id=?");
    $stmt->bind_param("i", $id);

    $stmt->execute()
        ? respond(200, "success", "Pet deleted successfully")
        : respond(500, "error", "Delete failed");
}
>>>>>>> Stashed changes
// INVALID
// if action is not valid i.e. does not exist, respond with error
else {
    respond(400, "error", "Invalid request action or action not yet implemented");
}

$conn->close();
?>
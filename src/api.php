<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require "db.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

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

function respond_compat($http_code, $payload, $status = "success", $message = "OK") {
    global $response_wrap;
    http_response_code($http_code);

    if ($response_wrap) {
        echo json_encode([
            "status" => $status,
            "message" => $message,
            "data" => $payload
        ]);
    } else {
        echo json_encode($payload);
    }
    exit;
}

function require_method($allowed_methods, $message = "Method Not Allowed") {
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    if (!in_array($method, $allowed_methods, true)) {
        respond(405, "error", $message);
    }
}

function read_request_payload() {
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    if ($method === "GET") {
        return $_GET;
    }

    $payload = $_POST;
    $raw = file_get_contents("php://input");
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = array_merge($payload, $decoded);
        } else {
            $form_payload = [];
            parse_str($raw, $form_payload);
            if (is_array($form_payload) && !empty($form_payload)) {
                $payload = array_merge($payload, $form_payload);
            }
        }
    }
    return $payload;
}

function read_input($payload, $key, $default = "") {
    if (array_key_exists($key, $payload)) {
        return $payload[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

function is_truthy($value) {
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ["1", "true", "yes", "on"], true);
}

function table_has_column($conn, $table, $column) {
    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

$payload = read_request_payload();
$action = trim((string) read_input($payload, "action", ""));
$username = trim((string) read_input($payload, "username", ""));
$password = (string) read_input($payload, "password", "");
$response_wrap = is_truthy(read_input($payload, "wrap", "0"));

// REGISTER function
//accepts username and password, checks if username exists, if not creates new user with hashed password
if ($action === "register") {
    require_method(["POST"]);
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
    require_method(["POST"]);
    if (!$username || !$password)
        respond(400, "error", "Please fill all fields");

    $stmt = $conn->prepare("SELECT id,password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0)
        respond(404, "error", "User not found");

    $user = $res->fetch_assoc();

    $stored_password = (string)($user["password"] ?? "");
    $is_password_valid = false;

    if ($stored_password !== "" && password_verify($password, $stored_password)) {
        $is_password_valid = true;
    } elseif ($stored_password !== "" && hash_equals($stored_password, $password)) {
        // Backward compatibility: allow legacy plain-text entries and migrate on login.
        $is_password_valid = true;
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $rehash_stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $rehash_stmt->bind_param("si", $new_hash, $user["id"]);
        $rehash_stmt->execute();
    }

    if ($is_password_valid) {
        respond(200, "success", "Login successful", [
            "user_id" => $user['id'],
            "username" => $username
        ]);
    } else {
        respond(401, "error", "Incorrect password");
    }
}
// ADD PET
// accepts user_id, pet_name and pet_type, validates them and adds new pet to database
elseif ($action === "add_pet") {
    require_method(["POST"]);
    $user_id = (int) read_input($payload, "user_id", 0);
    $pet_name = trim((string) read_input($payload, "pet_name", ""));
    $pet_type = trim((string) read_input($payload, "pet_type", ""));

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
    require_method(["POST", "DELETE"]);
    $id = (int) read_input($payload, "id", 0);

    if (!$id)
        respond(400, "error", "Select a pet to delete");

    $stmt = $conn->prepare("DELETE FROM pets WHERE id=?");
    $stmt->bind_param("i", $id);

    $stmt->execute()
        ? respond(200, "success", "Pet deleted successfully")
        : respond(500, "error", "Delete failed");
}

// DELETE USER
// accepts user id, validates it and deletes the user (pets cascade via FK)
elseif ($action === "delete_user") {
    require_method(["POST", "DELETE"]);
    $id = (int) read_input($payload, "id", 0);

    if (!$id)
        respond(400, "error", "Select a user to delete");

    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            respond(500, "error", "Delete failed");
        }

        if ($stmt->affected_rows > 0) {
            respond(200, "success", "User deleted successfully");
        }

        respond(404, "error", "User not found");
    } catch (Throwable $e) {
        respond(500, "error", "Delete failed");
    }
}


// GET PETS
// function that returns pets. user_id is an optional parameter; if left empty, it returns all pets.
elseif ($action === "get_pets") {
    require_method(["GET", "POST"]);
    $user_id = (int) read_input($payload, "user_id", 0);

    if ($user_id) {
        $stmt = $conn->prepare("SELECT pets.id, pets.id AS pet_id, pets.user_id, users.username, pet_name, pet_type FROM pets JOIN users ON pets.user_id = users.id WHERE pets.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query("SELECT pets.id, pets.id AS pet_id, pets.user_id, users.username, pet_name, pet_type FROM pets JOIN users ON pets.user_id = users.id");
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }

    respond(200, "success", count($data) > 0 ? "Pets retrieved" : "No pets found", $data);
}

// GET USERS
// returns all users; includes display_name / created_at only if those columns exist
elseif ($action === "get_users") {
    require_method(["GET", "POST"]);
    try {
        $has_display_name = table_has_column($conn, "users", "display_name");
        $has_created_at = table_has_column($conn, "users", "created_at");

        $query = "SELECT id, username";
        if ($has_display_name) $query .= ", display_name";
        if ($has_created_at) $query .= ", created_at";
        $query .= " FROM users";

        $res = $conn->query($query);
        $data = [];

        while ($row = $res->fetch_assoc()) {
            $user_item = [
                "id" => (int)$row["id"],
                "username" => $row["username"],
                "display_name" => $has_display_name ? $row["display_name"] : null,
                "created_at" => $has_created_at ? $row["created_at"] : null
            ];

            $data[] = $user_item;
        }

        respond(200, "success", count($data) > 0 ? "Users retrieved" : "No users found", $data);
    } catch (Throwable $e) {
        respond(500, "error", "Failed to fetch users");
    }
}

// UPDATE PET
// updates pet_name and/or pet_type for a given pet_id
elseif ($action === "update_pet") {
    require_method(["POST", "PUT", "PATCH"], "Only POST, PUT, or PATCH is allowed");
    $pet_id = (int) read_input($payload, "pet_id", 0);
    $pet_name = trim((string) read_input($payload, "pet_name", ""));
    $pet_type = trim((string) read_input($payload, "pet_type", ""));

    if ($pet_id <= 0) {
        respond_compat(400, [
            "status" => "error",
            "message" => "pet_id is required"
        ], "error", "pet_id is required");
    }

    if ($pet_name === '' && $pet_type === '') {
        respond_compat(400, [
            "status" => "error",
            "message" => "At least one field is required: pet_name or pet_type"
        ], "error", "At least one field is required: pet_name or pet_type");
    }

    try {
        if ($pet_name !== '' && $pet_type !== '') {
            $stmt = $conn->prepare("UPDATE pets SET pet_name=?, pet_type=? WHERE id=?");
            $stmt->bind_param("ssi", $pet_name, $pet_type, $pet_id);
        } elseif ($pet_name !== '') {
            $stmt = $conn->prepare("UPDATE pets SET pet_name=? WHERE id=?");
            $stmt->bind_param("si", $pet_name, $pet_id);
        } else {
            $stmt = $conn->prepare("UPDATE pets SET pet_type=? WHERE id=?");
            $stmt->bind_param("si", $pet_type, $pet_id);
        }

        if (!$stmt->execute()) {
            respond_compat(500, [
                "status" => "error",
                "message" => "Update failed"
            ], "error", "Update failed");
        }

        if ($stmt->affected_rows > 0) {
            respond_compat(200, [
                "status" => "success",
                "message" => "Pet updated successfully"
            ], "success", "Pet updated successfully");
        }

        respond_compat(200, [
            "status" => "warning",
            "message" => "No pet found or no changes made"
        ], "warning", "No pet found or no changes made");
    } catch (Throwable $e) {
        respond_compat(500, [
            "status" => "error",
            "message" => "Update failed"
        ], "error", "Update failed");
    }
}

// UPDATE USER
// updates username and/or password for a valid existing user_id
elseif ($action === "update_user") {
    require_method(["POST", "PUT", "PATCH"], "Only POST, PUT, or PATCH is allowed");

    $user_id = (int) read_input($payload, "user_id", 0);
    $username_update = trim((string) read_input($payload, "username", ""));
    $password_update = (string) read_input($payload, "password", "");

    if ($user_id <= 0) {
        respond_compat(400, [
            "status" => "error",
            "message" => "user_id is required"
        ], "error", "user_id is required");
    }

    $check_user = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $check_user->bind_param("i", $user_id);
    $check_user->execute();
    $check_user->store_result();

    if ($check_user->num_rows === 0) {
        respond_compat(404, [
            "status" => "error",
            "message" => "User not found"
        ], "error", "User not found");
    }

    if ($username_update === "" && $password_update === "") {
        respond_compat(400, [
            "status" => "error",
            "message" => "At least one field is required: username or password"
        ], "error", "At least one field is required: username or password");
    }

    if ($username_update !== "") {
        $check_username = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        $check_username->bind_param("si", $username_update, $user_id);
        $check_username->execute();
        $check_username->store_result();

        if ($check_username->num_rows > 0) {
            respond_compat(409, [
                "status" => "error",
                "message" => "Username already exists"
            ], "error", "Username already exists");
        }
    }

    try {
        if ($username_update !== "" && $password_update !== "") {
            $password_hash = password_hash($password_update, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username_update, $password_hash, $user_id);
        } elseif ($username_update !== "") {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $username_update, $user_id);
        } else {
            $password_hash = password_hash($password_update, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $password_hash, $user_id);
        }

        if (!$stmt->execute()) {
            respond_compat(500, [
                "status" => "error",
                "message" => "Update failed"
            ], "error", "Update failed");
        }

        if ($stmt->affected_rows > 0) {
            respond_compat(200, [
                "status" => "success",
                "message" => "User updated successfully"
            ], "success", "User updated successfully");
        }

        respond_compat(200, [
            "status" => "warning",
            "message" => "No user found or no changes made"
        ], "warning", "No user found or no changes made");
    } catch (Throwable $e) {
        respond_compat(500, [
            "status" => "error",
            "message" => "Update failed"
        ], "error", "Update failed");
    }
}


// INVALID
// if action is not valid i.e. does not exist, respond with error
else {
    respond(400, "error", "Invalid request action or action not yet implemented");
}

$conn->close();
?>

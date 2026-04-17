<?php
session_start();
$scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
$host = $_SERVER["HTTP_HOST"] ?? "localhost";
$scriptDir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/"));
$scriptDir = ($scriptDir === "/" || $scriptDir === ".") ? "" : rtrim($scriptDir, "/");
$apiCandidates = [
    $scheme . "://" . $host . $scriptDir . "/api.php",
    $scheme . "://" . $host . "/api.php",
    "http://localhost/pet-system-api/src/api.php",
    "http://127.0.0.1/pet-system-api/src/api.php",
    "http://localhost/api.php",
    "http://127.0.0.1/api.php",
    "http://localhost:8080/api.php",
    "http://127.0.0.1:8080/api.php"
];
$message = "";
$msgColor = "text-red-600";
$pets = [];
$redirectPath = strtok($_SERVER["REQUEST_URI"] ?? "client.php", "?");

if (isset($_SESSION["flash_message"])) {
    $message = (string)$_SESSION["flash_message"];
    $msgColor = (string)($_SESSION["flash_color"] ?? "text-red-600");
    unset($_SESSION["flash_message"], $_SESSION["flash_color"]);
}

function callAPI($data) {
    global $apiCandidates;
    $opts = ["http" => [
        "header" => "Content-type: application/x-www-form-urlencoded",
        "method" => "POST",
        "content" => http_build_query($data),
        "ignore_errors" => true,
        "timeout" => 10
    ]];

    $errors = [];
    $uniqueCandidates = array_values(array_unique($apiCandidates));
    foreach ($uniqueCandidates as $apiUrl) {
        $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
        if ($response === false) {
            $lastError = error_get_last();
            $details = $lastError["message"] ?? "Unable to connect";
            $errors[] = $apiUrl . " -> " . $details;
            continue;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $errors[] = $apiUrl . " -> Invalid JSON response";
    }

    return [
        "status" => "error",
        "message" => "API call failed. Tried endpoints: " . implode(" | ", $errors)
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"] ?? "";
    if($action == "logout") { session_destroy(); header("Location: " . $redirectPath); exit; }
    elseif($action == "login") {
        $res = callAPI($_POST);
        if (
            $res
            && ($res["status"] ?? "") == "success"
            && isset($res["data"]["user_id"], $res["data"]["username"])
        ) {
            $_SESSION["user_id"] = $res["data"]["user_id"];
            $_SESSION["username"] = $res["data"]["username"];
        }
        $message = $res["message"] ?? "Connection error";
        if (isset($res["status"]) && $res["status"] == "success") $msgColor = "text-green-600";
    }
    elseif($action == "update_user") {
        if (!isset($_SESSION["user_id"])) {
            $message = "Please login first";
        } else {
            $currentPassword = trim((string)($_POST["current_password"] ?? ""));
            $newPassword = trim((string)($_POST["password"] ?? ""));
            $confirmPassword = trim((string)($_POST["confirm_password"] ?? ""));

            if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
                $message = "Please fill in all password fields";
            } elseif ($newPassword !== $confirmPassword) {
                $message = "Passwords do not match";
            } elseif (!isset($_SESSION["username"])) {
                $message = "Unable to verify current password. Please login again.";
            } else {
                $auth = callAPI([
                    "action" => "login",
                    "username" => $_SESSION["username"],
                    "password" => $currentPassword
                ]);

                if (($auth["status"] ?? "") !== "success") {
                    $message = "Current password is incorrect";
                } else {
                    $res = callAPI([
                        "action" => "update_user",
                        "user_id" => $_SESSION["user_id"],
                        "password" => $newPassword
                    ]);
                    $message = $res["message"] ?? "Password update failed";
                    if (isset($res["status"]) && $res["status"] == "success") $msgColor = "text-green-600";
                }
            }
        }
    }
    else {
        $_POST["user_id"] = $_SESSION["user_id"] ?? null;
        $res = callAPI($_POST);
        $message = $res["message"] ?? "Action failed";
        if (isset($res["status"]) && $res["status"] == "success") $msgColor = "text-green-600";
    }

    $_SESSION["flash_message"] = $message;
    $_SESSION["flash_color"] = $msgColor;
    header("Location: " . $redirectPath);
    exit;
}

if (isset($_SESSION["user_id"])) {
    $res = callAPI(["action" => "get_pets", "user_id" => $_SESSION["user_id"]]);
    if ($res && $res["status"] == "success") $pets = $res["data"];
}

$petTypes = ["Dog", "Cat", "Bird", "Hamster", "Rabbit", "Fish"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Customer Panel</title>
</head>
<body class="bg-gray-50 flex justify-center items-center min-h-screen p-4 text-gray-800">

<div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 w-full max-w-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold tracking-tight"><?php echo isset($_SESSION["user_id"]) ? "Pet Dashboard" : "Customer Login"; ?></h2>
        <?php if(isset($_SESSION["user_id"])): ?>
            <form method="POST"><button name="action" value="logout" class="text-xs font-medium text-red-500 hover:text-red-700 uppercase tracking-wider">Logout</button></form>
        <?php endif; ?>
    </div>

    <?php if($message): ?>
        <p class="text-center text-sm font-medium mb-4 <?php echo $msgColor; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></p>
    <?php endif; ?>

    <?php if(!isset($_SESSION["user_id"])): ?>
        <form method="POST" class="space-y-3">
            <input name="username" placeholder="Username" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition">
            <input name="password" type="password" placeholder="Password" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition">
            <div class="flex gap-2 pt-2">
                <button name="action" value="register" class="flex-1 bg-gray-100 text-gray-700 p-3 rounded-xl font-semibold hover:bg-gray-200 transition">Register</button>
                <button name="action" value="login" class="flex-1 bg-blue-600 text-white p-3 rounded-xl font-semibold hover:bg-blue-700 transition">Login</button>
            </div>
        </form>
    <?php else: ?>
        <form method="POST" class="bg-gray-50 p-4 rounded-2xl border border-gray-100 mb-6">
            <h3 class="text-sm font-bold mb-3 uppercase text-gray-400 tracking-widest">Add New Pet</h3>
            <div class="flex flex-col gap-2">
                <input name="pet_name" placeholder="Pet Name" required class="w-full p-2.5 bg-white border border-gray-200 rounded-lg text-sm">
                <select name="pet_type" required class="w-full p-2.5 bg-white border border-gray-200 rounded-lg text-sm">
                    <option value="" disabled selected>Select Type</option>
                    <?php foreach($petTypes as $type): ?> <option value="<?php echo $type; ?>"><?php echo $type; ?></option> <?php endforeach; ?>
                </select>
                <button name="action" value="add_pet" class="w-full bg-gray-900 text-white p-2.5 rounded-lg text-sm font-bold hover:bg-black transition">Add Pet</button>
            </div>
        </form>

        <form method="POST" class="bg-gray-50 p-4 rounded-2xl border border-gray-100 mb-6">
            <h3 class="text-sm font-bold mb-3 uppercase text-gray-400 tracking-widest">Account Security</h3>
            <div class="flex flex-col gap-2">
                <input name="current_password" type="password" placeholder="Current Password" required class="w-full p-2.5 bg-white border border-gray-200 rounded-lg text-sm">
                <input name="password" type="password" placeholder="New Password" required class="w-full p-2.5 bg-white border border-gray-200 rounded-lg text-sm">
                <input name="confirm_password" type="password" placeholder="Confirm New Password" required class="w-full p-2.5 bg-white border border-gray-200 rounded-lg text-sm">
                <button name="action" value="update_user" class="w-full bg-blue-600 text-white p-2.5 rounded-lg text-sm font-bold hover:bg-blue-700 transition">Update Password</button>
            </div>
        </form>

        <h3 class="text-sm font-bold mb-3 uppercase text-gray-400 tracking-widest">My Pets</h3>
        <div class="space-y-3">
            <?php if(empty($pets)): ?>
                <p class="text-center text-xs text-gray-400 italic py-4">No pets added yet.</p>
            <?php endif; ?>
            <?php foreach($pets as $p): ?>
                <div class="flex items-center justify-between p-4 bg-white border border-gray-100 rounded-xl shadow-sm hover:shadow-md transition">
                    <div>
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($p["pet_name"]); ?></p>
                        <p class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($p["pet_type"]); ?></p>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="openEditModal('<?php echo $p['id']; ?>', '<?php echo $p['pet_name']; ?>', '<?php echo $p['pet_type']; ?>')" class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </button>
                        <button onclick="openDeleteModal('<?php echo $p['id']; ?>', '<?php echo $p['pet_name']; ?>')" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white rounded-2xl p-6 w-full max-w-xs shadow-2xl">
        <h3 class="font-bold text-lg mb-4">Edit Pet</h3>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="pet_id" id="edit_pet_id">
            <input name="pet_name" id="edit_name" required class="w-full p-2.5 border rounded-lg text-sm">
            <select name="pet_type" id="edit_type" required class="w-full p-2.5 border rounded-lg text-sm">
                <?php foreach($petTypes as $type): ?> <option value="<?php echo $type; ?>"><?php echo $type; ?></option> <?php endforeach; ?>
            </select>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeModal('editModal')" class="flex-1 text-sm font-bold text-gray-500">Cancel</button>
                <button name="action" value="update_pet" class="flex-1 bg-blue-600 text-white p-2.5 rounded-lg text-sm font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white rounded-2xl p-6 w-full max-w-xs shadow-2xl text-center">
        <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
        </div>
        <h3 class="font-bold text-lg mb-1">Delete Pet?</h3>
        <p class="text-sm text-gray-500 mb-6">Are you sure you want to remove <span id="del_name" class="font-bold"></span>?</p>
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="id" id="del_id">
            <button type="button" onclick="closeModal('deleteModal')" class="flex-1 p-2.5 text-sm font-bold text-gray-400">Cancel</button>
            <button name="action" value="delete_pet" class="flex-1 bg-red-600 text-white p-2.5 rounded-lg text-sm font-bold">Delete</button>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, name, type) {
        document.getElementById('edit_pet_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_type').value = type;
        document.getElementById('editModal').classList.remove('hidden');
    }
    function openDeleteModal(id, name) {
        document.getElementById('del_id').value = id;
        document.getElementById('del_name').innerText = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>
</body>
</html>

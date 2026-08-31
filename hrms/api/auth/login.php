<?php

session_start();

header("Content-Type: application/json");

require_once "../../config/database.php";

$response = [
    "success" => false,
    "message" => ""
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    $response["message"] = "Only POST requests are allowed.";

    echo json_encode($response);
    exit;
}

/*
 * Accept JSON requests from Agatha's frontend
 */
$input = json_decode(file_get_contents("php://input"), true);

/*
 * Also support normal form POST data
 * so we can easily test the API.
 */
if (!is_array($input)) {
    $input = $_POST;
}

$username = trim($input["username"] ?? "");
$password = $input["password"] ?? "";

if ($username === "" || $password === "") {

    http_response_code(400);

    $response["message"] = "Username and password are required.";

    echo json_encode($response);
    exit;
}

$sql = "SELECT
            u.user_id,
            u.employee_id,
            u.role_id,
            u.username,
            u.email,
            u.password_hash,
            u.account_status,
            r.role_name
        FROM users u

        INNER JOIN roles r
            ON u.role_id = r.role_id

        WHERE u.username = ?

        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    $response["message"] = "Database query preparation failed.";

    echo json_encode($response);
    exit;
}

$stmt->bind_param("s", $username);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    http_response_code(401);

    $response["message"] = "Invalid username or password.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

$user = $result->fetch_assoc();

/*
 * Check whether the account is active.
 */
if ($user["account_status"] !== "Active") {

    http_response_code(403);

    $response["message"] = "This account is not active.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

/*
 * Verify the password against the stored hash.
 */
if (!password_verify($password, $user["password_hash"])) {

    http_response_code(401);

    $response["message"] = "Invalid username or password.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

/*
 * Prevent session fixation.
 */
session_regenerate_id(true);

/*
 * Store authenticated user information.
 */
$_SESSION["user_id"] = $user["user_id"];
$_SESSION["employee_id"] = $user["employee_id"];
$_SESSION["role_id"] = $user["role_id"];
$_SESSION["username"] = $user["username"];
$_SESSION["role_name"] = $user["role_name"];

/*
 * Record the latest login.
 */
$update_sql = "UPDATE users
               SET last_login = CURRENT_TIMESTAMP
               WHERE user_id = ?";

$update_stmt = $conn->prepare($update_sql);

if ($update_stmt) {

    $update_stmt->bind_param("i", $user["user_id"]);
    $update_stmt->execute();
    $update_stmt->close();
}

/*
 * Successful response.
 */
$response["success"] = true;
$response["message"] = "Login successful.";

$response["user"] = [
    "user_id" => $user["user_id"],
    "employee_id" => $user["employee_id"],
    "username" => $user["username"],
    "email" => $user["email"],
    "role_id" => $user["role_id"],
    "role_name" => $user["role_name"]
];

$stmt->close();
$conn->close();

echo json_encode($response);

?>
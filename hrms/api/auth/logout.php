<?php

require_once "../../includes/cors.php";

session_start();

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);

    exit;
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $session_cookie_parameters = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $session_cookie_parameters["path"],
        $session_cookie_parameters["domain"],
        $session_cookie_parameters["secure"],
        $session_cookie_parameters["httponly"]
    );
}

if (!session_destroy()) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to log out."
    ]);

    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Logged out successfully."
]);

?>

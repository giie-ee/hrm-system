<?php

require_once "../../includes/cors.php";
require_once "../../includes/session.php";

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

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $session_cookie_parameters['path'],
        'domain' => $session_cookie_parameters['domain'],
        'secure' => $session_cookie_parameters['secure'],
        'httponly' => $session_cookie_parameters['httponly'],
        'samesite' => 'Lax',
    ]);
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

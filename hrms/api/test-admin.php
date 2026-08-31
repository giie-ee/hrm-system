<?php

require_once "../includes/cors.php";

header("Content-Type: application/json");

require_once "../includes/auth.php";

requireRole(["Admin"]);

echo json_encode([
    "success" => true,
    "message" => "Welcome, Administrator. You have access to this resource."
]);

?>
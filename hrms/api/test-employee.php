<?php

header("Content-Type: application/json");

require_once "../includes/auth.php";

requireRole(["Employee"]);

echo json_encode([
    "success" => true,
    "message" => "Welcome, Employee. You have access to this resource."
]);

?>
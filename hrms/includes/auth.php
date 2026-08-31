<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check whether a user is logged in.
 */
function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authentication required."
        ]);

        exit;
    }
}

/**
 * Check whether the logged-in user has
 * one of the required roles.
 */
function requireRole($allowedRoles)
{
    requireLogin();

    $userRole = $_SESSION["role_name"] ?? "";

    if (!in_array($userRole, $allowedRoles, true)) {

        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Access denied."
        ]);

        exit;
    }
}
?>
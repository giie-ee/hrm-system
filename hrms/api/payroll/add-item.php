<?php

require_once "../../includes/cors.php";

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();

$user_role = $_SESSION["role_name"] ?? "";

if (!in_array($user_role, ["Admin", "HR"], true)) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);

    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$payroll_id = $data["payroll_id"] ?? null;
$item_type = $data["item_type"] ?? null;
$item_name = $data["item_name"] ?? null;
$amount = $data["amount"] ?? null;
$description = $data["description"] ?? null;


/*
 * Validate required fields
 */

if (!$payroll_id || !$item_type || !$item_name || $amount === null) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Payroll ID, item type, item name and amount are required."
    ]);

    exit;
}


/*
 * Validate item type
 */

if ($item_type !== "Allowance" && $item_type !== "Deduction") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Item type must be Allowance or Deduction."
    ]);

    exit;
}


/*
 * Validate amount
 */

if (!is_numeric($amount) || $amount < 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Amount must be a valid positive number."
    ]);

    exit;
}


try {

    /*
     * Check that the payroll exists.
     */

    $check_sql = "
        SELECT payroll_id
        FROM payroll
        WHERE payroll_id = ?
        LIMIT 1
    ";

    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $payroll_id);
    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Payroll record not found."
        ]);

        exit;
    }


    /*
     * Add the payroll item.
     */

    $insert_sql = "
        INSERT INTO payroll_items
        (
            payroll_id,
            item_type,
            item_name,
            amount,
            description
        )
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($insert_sql);

    $amount = (float)$amount;

    $stmt->bind_param(
        "issds",
        $payroll_id,
        $item_type,
        $item_name,
        $amount,
        $description
    );

    $stmt->execute();


    echo json_encode([
        "success" => true,
        "message" => "Payroll item added successfully.",
        "payroll_item_id" => $stmt->insert_id,
        "payroll_id" => $payroll_id,
        "item_type" => $item_type,
        "item_name" => $item_name,
        "amount" => $amount,
        "description" => $description
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to add payroll item.",
        "error" => $e->getMessage()
    ]);
}

?>
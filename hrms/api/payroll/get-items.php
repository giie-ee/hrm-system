<?php

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);

    exit;
}

$payroll_id = $_GET["payroll_id"] ?? null;

if (!$payroll_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Payroll ID is required."
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
     * Get all items belonging to this payroll.
     */

    $sql = "
        SELECT
            payroll_item_id,
            payroll_id,
            item_type,
            item_name,
            amount,
            description,
            created_at
        FROM payroll_items
        WHERE payroll_id = ?
        ORDER BY payroll_item_id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $items = [];

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode([
        "success" => true,
        "message" => "Payroll items retrieved successfully.",
        "payroll_id" => (int)$payroll_id,
        "total_items" => count($items),
        "items" => $items
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve payroll items.",
        "error" => $e->getMessage()
    ]);
}

?>
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
     * Get the payroll record.
     */
    $sql = "
        SELECT
            payroll_id,
            employee_id,
            pay_period_start,
            pay_period_end,
            basic_salary,
            payroll_status
        FROM payroll
        WHERE payroll_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Payroll record not found."
        ]);

        exit;
    }

    $payroll = $result->fetch_assoc();

    $employee_id = (int)$payroll["employee_id"];
    $basic_salary = (float)$payroll["basic_salary"];

    /*
     * Get ONLY the items belonging to this payroll.
     */
    $items_sql = "
        SELECT
            payroll_item_id,
            payroll_id,
            item_type,
            item_name,
            amount,
            description
        FROM payroll_items
        WHERE payroll_id = ?
        ORDER BY payroll_item_id ASC
    ";

    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $payroll_id);
    $items_stmt->execute();

    $items_result = $items_stmt->get_result();

    $total_allowances = 0;
    $total_deductions = 0;
    $payroll_items = [];

    while ($item = $items_result->fetch_assoc()) {

        $amount = (float)$item["amount"];

        if ($item["item_type"] === "Allowance") {

            $total_allowances += $amount;

        } elseif ($item["item_type"] === "Deduction") {

            $total_deductions += $amount;
        }

        $payroll_items[] = [
            "item_type" => $item["item_type"],
            "item_name" => $item["item_name"],
            "amount" => $amount,
            "description" => $item["description"]
        ];
    }

    /*
     * Calculate payroll.
     */
    $gross_salary = $basic_salary + $total_allowances;

    $net_salary = $gross_salary - $total_deductions;

    /*
     * Update the existing payroll record.
     */
    $update_sql = "
        UPDATE payroll
        SET
            total_allowances = ?,
            total_deductions = ?,
            gross_salary = ?,
            net_salary = ?
        WHERE payroll_id = ?
    ";

    $update_stmt = $conn->prepare($update_sql);

    $update_stmt->bind_param(
        "ddddi",
        $total_allowances,
        $total_deductions,
        $gross_salary,
        $net_salary,
        $payroll_id
    );

    $update_stmt->execute();

    /*
     * Return the final calculation.
     */
    echo json_encode([
        "success" => true,
        "message" => "Payroll calculated successfully.",
        "payroll_id" => $payroll_id,
        "employee_id" => $employee_id,
        "basic_salary" => $basic_salary,
        "total_allowances" => $total_allowances,
        "total_deductions" => $total_deductions,
        "gross_salary" => $gross_salary,
        "net_salary" => $net_salary,
        "payroll_status" => $payroll["payroll_status"],
        "payroll_items" => $payroll_items
    ]);

    $items_stmt->close();
    $stmt->close();
    $update_stmt->close();

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Payroll calculation failed.",
        "error" => $e->getMessage()
    ]);
}

?>
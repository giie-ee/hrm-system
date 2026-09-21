<?php

require_once "../../includes/cors.php";
require_once "../../includes/session.php";

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

$employee_id = $data["employee_id"] ?? null;
$pay_period_start = $data["pay_period_start"] ?? null;
$pay_period_end = $data["pay_period_end"] ?? null;

if (!$employee_id || !$pay_period_start || !$pay_period_end) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Employee ID and payroll period are required."
    ]);

    exit;
}

try {

    /*
     * Get the employee's active basic salary.
     */

    $salary_sql = "
        SELECT basic_salary
        FROM employee_salaries
        WHERE employee_id = ?
        AND salary_status = 'Active'
        ORDER BY effective_from DESC
        LIMIT 1
    ";

    $salary_stmt = $conn->prepare($salary_sql);

    if (!$salary_stmt) {
        throw new Exception("Failed to prepare salary query.");
    }

    $salary_stmt->bind_param("i", $employee_id);
    $salary_stmt->execute();

    $salary_result = $salary_stmt->get_result();

    if ($salary_result->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "No active salary found for this employee."
        ]);

        exit;
    }

    $salary = $salary_result->fetch_assoc();

    $basic_salary = (float)$salary["basic_salary"];

    $salary_stmt->close();


    /*
     * Create a new Draft payroll.
     */

    $insert_sql = "
        INSERT INTO payroll
        (
            employee_id,
            pay_period_start,
            pay_period_end,
            basic_salary,
            total_allowances,
            total_deductions,
            gross_salary,
            net_salary,
            payroll_status
        )
        VALUES (?, ?, ?, ?, 0, 0, ?, ?, 'Draft')
    ";

    /*
     * Initial gross and net are equal to basic salary.
     * They will be updated after payroll items are added
     * and calculate.php is executed.
     */

    $gross_salary = $basic_salary;
    $net_salary = $basic_salary;

    $insert_stmt = $conn->prepare($insert_sql);

    if (!$insert_stmt) {
        throw new Exception(
            "Failed to prepare payroll creation."
        );
    }

    $insert_stmt->bind_param(
        "issddd",
        $employee_id,
        $pay_period_start,
        $pay_period_end,
        $basic_salary,
        $gross_salary,
        $net_salary
    );

    $insert_stmt->execute();

    $payroll_id = $insert_stmt->insert_id;

    $insert_stmt->close();


    /*
     * Successful response.
     */

    echo json_encode([
        "success" => true,
        "message" => "Payroll created successfully.",
        "payroll_id" => $payroll_id,
        "employee_id" => (int)$employee_id,
        "pay_period_start" => $pay_period_start,
        "pay_period_end" => $pay_period_end,
        "basic_salary" => $basic_salary,
        "total_allowances" => 0,
        "total_deductions" => 0,
        "gross_salary" => $gross_salary,
        "net_salary" => $net_salary,
        "payroll_status" => "Draft"
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Payroll creation failed.",
        "error" => $e->getMessage()
    ]);
}

?>

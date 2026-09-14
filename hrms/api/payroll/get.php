<?php

require_once "../../includes/cors.php";

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();

$user_role = $_SESSION["role_name"] ?? "";
$session_employee_id = null;

if ($user_role === "Employee") {

    $session_employee_id = $_SESSION["employee_id"] ?? null;

    if (
        $session_employee_id === null ||
        !filter_var($session_employee_id, FILTER_VALIDATE_INT) ||
        (int)$session_employee_id <= 0
    ) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authenticated employee information is unavailable."
        ]);

        exit;
    }

    $session_employee_id = (int)$session_employee_id;
} elseif (!in_array($user_role, ["Admin", "HR", "Manager"], true)) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);

    exit;
}

$employee_id = $_GET["employee_id"] ?? null;

if ($user_role === "Employee") {
    $employee_id = $session_employee_id;
} elseif ($employee_id !== null && $employee_id !== "") {

    if (
        !filter_var($employee_id, FILTER_VALIDATE_INT) ||
        (int)$employee_id <= 0
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Employee ID must be a valid positive integer."
        ]);

        exit;
    }

    $employee_id = (int)$employee_id;
}

try {

    $sql = "
        SELECT
            p.payroll_id,
            p.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            p.pay_period_start,
            p.pay_period_end,
            p.payroll_status,
            p.basic_salary,
            p.total_allowances,
            p.total_deductions,
            p.gross_salary,
            p.net_salary,
            p.payment_date,
            p.created_at,
            p.updated_at
        FROM payroll p
        INNER JOIN employees e
            ON p.employee_id = e.employee_id
    ";

    $conditions = [];
    $parameters = [];
    $types = "";

    if ($employee_id !== null && $employee_id !== "") {
        $conditions[] = "p.employee_id = ?";
        $parameters[] = $employee_id;
        $types .= "i";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.created_at DESC, p.payroll_id DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare payroll query.");
    }

    if (!empty($parameters)) {
        $bind_values = [];
        $bind_values[] = $types;

        foreach ($parameters as $key => $value) {
            $bind_values[] = &$parameters[$key];
        }

        call_user_func_array(
            [$stmt, "bind_param"],
            $bind_values
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to retrieve payroll records.");
    }

    $result = $stmt->get_result();
    $payroll_records = [];

    while ($row = $result->fetch_assoc()) {
        $payroll_records[] = [
            "payroll_id" => (int)$row["payroll_id"],
            "employee_id" => (int)$row["employee_id"],
            "employee_name" => $row["employee_name"],
            "pay_period_start" => $row["pay_period_start"],
            "pay_period_end" => $row["pay_period_end"],
            "payroll_status" => $row["payroll_status"],
            "basic_salary" => (float)$row["basic_salary"],
            "total_allowances" => (float)$row["total_allowances"],
            "total_deductions" => (float)$row["total_deductions"],
            "gross_salary" => (float)$row["gross_salary"],
            "net_salary" => (float)$row["net_salary"],
            "payment_date" => $row["payment_date"],
            "created_at" => $row["created_at"],
            "updated_at" => $row["updated_at"]
        ];
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" => "Payroll records retrieved successfully.",
        "count" => count($payroll_records),
        "data" => $payroll_records
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve payroll records."
    ]);
}

?>

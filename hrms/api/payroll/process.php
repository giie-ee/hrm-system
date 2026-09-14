<?php

require_once "../../includes/cors.php";

session_start();

header("Content-Type: application/json");

require_once "../../config/database.php";

$response = [
    "success" => false,
    "message" => ""
];

/*
 * Only POST requests are allowed.
 */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    $response["message"] = "Only POST requests are allowed.";

    echo json_encode($response);
    exit;
}

/*
 * Check authentication.
 */
if (!isset($_SESSION["user_id"])) {

    http_response_code(401);

    $response["message"] = "Authentication required.";

    echo json_encode($response);
    exit;
}

$user_role = $_SESSION["role_name"] ?? "";

if (!in_array($user_role, ["Admin", "HR"], true)) {

    http_response_code(403);

    $response["message"] = "Access denied.";

    echo json_encode($response);
    exit;
}

/*
 * Read JSON request.
 */
$input = json_decode(file_get_contents("php://input"), true);

/*
 * Also support normal form POST data.
 */
if (!is_array($input)) {
    $input = $_POST;
}

$payroll_id = (int)($input["payroll_id"] ?? 0);

if ($payroll_id <= 0) {

    http_response_code(400);

    $response["message"] = "A valid payroll_id is required.";

    echo json_encode($response);
    exit;
}

/*
 * Find the payroll record.
 */
$sql = "SELECT
            payroll_id,
            employee_id,
            pay_period_start,
            pay_period_end,
            basic_salary,
            total_allowances,
            total_deductions,
            gross_salary,
            net_salary,
            payroll_status,
            payment_date
        FROM payroll
        WHERE payroll_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    $response["message"] = "Database query preparation failed.";

    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $payroll_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    http_response_code(404);

    $response["message"] = "Payroll record not found.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

$payroll = $result->fetch_assoc();

/*
 * Prevent processing an already processed payroll.
 */
if ($payroll["payroll_status"] === "Processed") {

    http_response_code(400);

    $response["message"] = "This payroll has already been processed.";

    $response["payroll_id"] = $payroll["payroll_id"];
    $response["payroll_status"] = $payroll["payroll_status"];

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

/*
 * Only Draft payrolls can be processed.
 */
if ($payroll["payroll_status"] !== "Draft") {

    http_response_code(400);

    $response["message"] = "Only Draft payrolls can be processed.";

    $response["payroll_id"] = $payroll["payroll_id"];
    $response["payroll_status"] = $payroll["payroll_status"];

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

/*
 * Process the payroll.
 */
$update_sql = "UPDATE payroll
               SET payroll_status = 'Processed',
                   payment_date = CURRENT_DATE
               WHERE payroll_id = ?";

$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {

    http_response_code(500);

    $response["message"] = "Failed to prepare payroll processing.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

$update_stmt->bind_param("i", $payroll_id);

if (!$update_stmt->execute()) {

    http_response_code(500);

    $response["message"] = "Failed to process payroll.";

    $update_stmt->close();
    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

/*
 * Successful response.
 */
$response["success"] = true;
$response["message"] = "Payroll processed successfully.";

$response["payroll_id"] = $payroll["payroll_id"];
$response["employee_id"] = $payroll["employee_id"];
$response["basic_salary"] = (float)$payroll["basic_salary"];
$response["total_allowances"] = (float)$payroll["total_allowances"];
$response["total_deductions"] = (float)$payroll["total_deductions"];
$response["gross_salary"] = (float)$payroll["gross_salary"];
$response["net_salary"] = (float)$payroll["net_salary"];
$response["payroll_status"] = "Processed";
$response["payment_date"] = date("Y-m-d");

$update_stmt->close();
$stmt->close();
$conn->close();

echo json_encode($response);

?>
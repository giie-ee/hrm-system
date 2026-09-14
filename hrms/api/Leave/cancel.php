<?php

require_once "../../includes/cors.php";

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON request body."
    ]);
    exit;
}

$leave_request_id = $data["leave_request_id"] ?? null;

if (
    $leave_request_id === null ||
    !filter_var($leave_request_id, FILTER_VALIDATE_INT) ||
    (int)$leave_request_id <= 0
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid leave_request_id."
    ]);
    exit;
}

$leave_request_id = (int)$leave_request_id;
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

$conn->begin_transaction();

try {

    // Lock the request while processing it
    $stmt = $conn->prepare("
        SELECT
            leave_request_id,
            employee_id,
            leave_type_id,
            start_date,
            end_date,
            number_of_days,
            status
        FROM leave_requests
        WHERE leave_request_id = ?
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare request lookup.");
    }

    $stmt->bind_param("i", $leave_request_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to retrieve leave request.");
    }

    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    $stmt->close();

    if (!$request) {
        $conn->rollback();

        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Leave request not found."
        ]);
        exit;
    }

    // Only Pending requests can be cancelled
    if ($request["status"] !== "Pending") {
        $conn->rollback();

        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Only Pending leave requests can be cancelled.",
            "current_status" => $request["status"]
        ]);
        exit;
    }

    if (
        $user_role === "Employee" &&
        (int)$request["employee_id"] !== $session_employee_id
    ) {
        $conn->rollback();

        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Employees may only cancel their own leave requests."
        ]);
        exit;
    }

    // Cancel the request
    $stmt = $conn->prepare("
        UPDATE leave_requests
        SET
            status = 'Cancelled',
            updated_at = CURRENT_TIMESTAMP
        WHERE leave_request_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare cancellation.");
    }

    $stmt->bind_param("i", $leave_request_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to cancel leave request.");
    }

    $stmt->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Leave request cancelled successfully.",
        "data" => [
            "leave_request_id" => $request["leave_request_id"],
            "employee_id" => $request["employee_id"],
            "leave_type_id" => $request["leave_type_id"],
            "start_date" => $request["start_date"],
            "end_date" => $request["end_date"],
            "number_of_days" => $request["number_of_days"],
            "status" => "Cancelled"
        ]
    ]);

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to cancel leave request."
    ]);
}

?>
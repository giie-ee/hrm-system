<?php

require_once "../../includes/cors.php";

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();

$user_role = $_SESSION["role_name"] ?? "";

if (!in_array($user_role, ["Admin", "HR", "Manager"], true)) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;
}


/*
 * Only POST requests are allowed.
 */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);

    exit;
}


/*
 * Read JSON request body.
 */
$data = json_decode(file_get_contents("php://input"), true);


/*
 * Validate JSON.
 */
if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON request body."
    ]);

    exit;
}


/*
 * Required fields.
 */
$leave_request_id = $data["leave_request_id"] ?? null;
$rejection_reason = $data["rejection_reason"] ?? null;


/*
 * Validate leave request ID.
 */
if (
    $leave_request_id === null ||
    !filter_var($leave_request_id, FILTER_VALIDATE_INT) ||
    $leave_request_id <= 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid leave request ID is required."
    ]);

    exit;
}


/*
 * Rejection reason is mandatory.
 */
if (
    $rejection_reason === null ||
    trim($rejection_reason) === ""
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A rejection reason is required."
    ]);

    exit;
}


$rejection_reason = trim($rejection_reason);


/*
 * Prevent excessively long rejection reasons.
 */
if (strlen($rejection_reason) > 5000) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Rejection reason is too long."
    ]);

    exit;
}


/*
 * Get authenticated user.
 *
 * Our existing authentication system stores
 * the logged-in user's ID in the session.
 */
$reviewed_by = $_SESSION["user_id"] ?? null;


if (
    $reviewed_by === null ||
    !filter_var($reviewed_by, FILTER_VALIDATE_INT) ||
    $reviewed_by <= 0
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Authenticated user information is unavailable."
    ]);

    exit;
}


try {

    /*
     * Start transaction.
     *
     * Rejection does not modify the leave balance,
     * but the request status and reviewer information
     * should still be updated atomically.
     */
    $conn->begin_transaction();


    /*
     * STEP 1:
     * Retrieve and lock the leave request.
     */
    $request_sql = "
        SELECT
            leave_request_id,
            employee_id,
            leave_type_id,
            start_date,
            end_date,
            number_of_days,
            reason,
            status
        FROM leave_requests
        WHERE leave_request_id = ?
        LIMIT 1
        FOR UPDATE
    ";

    $request_stmt = $conn->prepare($request_sql);

    if (!$request_stmt) {
        throw new Exception("Failed to prepare leave request query.");
    }

    $request_stmt->bind_param(
        "i",
        $leave_request_id
    );

    $request_stmt->execute();

    $request_result = $request_stmt->get_result();


    /*
     * Request does not exist.
     */
    if ($request_result->num_rows === 0) {

        $conn->rollback();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Leave request not found."
        ]);

        exit;
    }


    $request = $request_result->fetch_assoc();


    /*
     * Only Pending requests can be rejected.
     */
    if ($request["status"] !== "Pending") {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Only Pending leave requests can be rejected.",
            "current_status" => $request["status"]
        ]);

        exit;
    }


    /*
     * STEP 2:
     * Reject the request.
     *
     * IMPORTANT:
     * We deliberately DO NOT modify leave_balances.
     *
     * A rejected request never consumes leave days.
     */
    $reject_sql = "
        UPDATE leave_requests
        SET
            status = 'Rejected',
            approved_by = ?,
            approved_at = NOW(),
            rejection_reason = ?
        WHERE leave_request_id = ?
        AND status = 'Pending'
    ";

    $reject_stmt = $conn->prepare($reject_sql);

    if (!$reject_stmt) {
        throw new Exception("Failed to prepare leave rejection.");
    }

    $reject_stmt->bind_param(
        "isi",
        $reviewed_by,
        $rejection_reason,
        $leave_request_id
    );

    $reject_stmt->execute();


    /*
     * Confirm exactly one request was rejected.
     */
    if ($reject_stmt->affected_rows !== 1) {
        throw new Exception("Leave request could not be rejected.");
    }


    /*
     * STEP 3:
     * Commit the rejection.
     */
    $conn->commit();


    /*
     * Return successful response.
     */
    echo json_encode([
        "success" => true,
        "message" => "Leave request rejected successfully.",
        "data" => [
            "leave_request_id" => (int)$leave_request_id,
            "employee_id" => (int)$request["employee_id"],
            "leave_type_id" => (int)$request["leave_type_id"],
            "start_date" => $request["start_date"],
            "end_date" => $request["end_date"],
            "number_of_days" => (int)$request["number_of_days"],
            "status" => "Rejected",
            "reviewed_by" => (int)$reviewed_by,
            "rejection_reason" => $rejection_reason
        ]
    ]);

} catch (Exception $e) {

    /*
     * Roll back if anything fails.
     */
    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to reject leave request."
    ]);

}

?>
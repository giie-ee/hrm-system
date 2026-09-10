<?php

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();


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
 * Leave request ID is required.
 */
$leave_request_id = $data["leave_request_id"] ?? null;


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
 * The authenticated user approving the request.
 *
 * auth.php/login.php stores user_id in the session.
 */
$approved_by = $_SESSION["user_id"] ?? null;


if (
    $approved_by === null ||
    !filter_var($approved_by, FILTER_VALIDATE_INT) ||
    $approved_by <= 0
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
     * Approval and balance deduction must succeed
     * together or fail together.
     */
    $conn->begin_transaction();


    /*
     * STEP 1:
     * Retrieve and lock the leave request.
     *
     * FOR UPDATE prevents another transaction from
     * modifying this request at the same time.
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
     * Only Pending requests can be approved.
     */
    if ($request["status"] !== "Pending") {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Only Pending leave requests can be approved.",
            "current_status" => $request["status"]
        ]);

        exit;
    }


    $employee_id = (int)$request["employee_id"];
    $leave_type_id = (int)$request["leave_type_id"];
    $number_of_days = (int)$request["number_of_days"];
    $start_date = $request["start_date"];
    $end_date = $request["end_date"];

    $leave_year = (int)date(
        "Y",
        strtotime($start_date)
    );


    /*
     * STEP 2:
     * Lock the employee's leave balance.
     */
    $balance_sql = "
        SELECT
            leave_balance_id,
            total_days,
            used_days,
            remaining_days
        FROM leave_balances
        WHERE employee_id = ?
        AND leave_type_id = ?
        AND year = ?
        LIMIT 1
        FOR UPDATE
    ";

    $balance_stmt = $conn->prepare($balance_sql);

    if (!$balance_stmt) {
        throw new Exception("Failed to prepare leave balance query.");
    }

    $balance_stmt->bind_param(
        "iii",
        $employee_id,
        $leave_type_id,
        $leave_year
    );

    $balance_stmt->execute();

    $balance_result = $balance_stmt->get_result();


    /*
     * No balance exists.
     */
    if ($balance_result->num_rows === 0) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "No leave balance exists for this employee for the selected year."
        ]);

        exit;
    }


    $balance = $balance_result->fetch_assoc();

    $used_days = (int)$balance["used_days"];
    $remaining_days = (int)$balance["remaining_days"];


    /*
     * STEP 3:
     * Re-check the balance.
     *
     * We check again during approval because the
     * balance may have changed after the original
     * request was submitted.
     */
    if ($number_of_days > $remaining_days) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Insufficient leave balance for approval.",
            "data" => [
                "requested_days" => $number_of_days,
                "remaining_days" => $remaining_days
            ]
        ]);

        exit;
    }


    /*
     * STEP 4:
     * Re-check for overlapping APPROVED leave.
     *
     * We exclude the current request.
     *
     * At approval time we protect the employee from
     * having two approved leave periods at once.
     */
    $overlap_sql = "
        SELECT
            leave_request_id,
            leave_type_id,
            start_date,
            end_date
        FROM leave_requests
        WHERE employee_id = ?
        AND leave_request_id <> ?
        AND status = 'Approved'
        AND start_date <= ?
        AND end_date >= ?
        LIMIT 1
        FOR UPDATE
    ";

    $overlap_stmt = $conn->prepare($overlap_sql);

    if (!$overlap_stmt) {
        throw new Exception("Failed to prepare leave overlap check.");
    }

    $overlap_stmt->bind_param(
        "iiss",
        $employee_id,
        $leave_request_id,
        $end_date,
        $start_date
    );

    $overlap_stmt->execute();

    $overlap_result = $overlap_stmt->get_result();


    if ($overlap_result->num_rows > 0) {

        $existing_leave = $overlap_result->fetch_assoc();

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This leave request overlaps with an already approved leave period.",
            "data" => [
                "leave_request_id" => (int)$existing_leave["leave_request_id"],
                "start_date" => $existing_leave["start_date"],
                "end_date" => $existing_leave["end_date"]
            ]
        ]);

        exit;
    }


    /*
     * STEP 5:
     * Calculate the new balance.
     */
    $new_used_days = $used_days + $number_of_days;
    $new_remaining_days = $remaining_days - $number_of_days;


    /*
     * Safety check.
     */
    if ($new_remaining_days < 0) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Leave balance cannot become negative."
        ]);

        exit;
    }


    /*
     * STEP 6:
     * Update leave balance.
     */
    $balance_update_sql = "
        UPDATE leave_balances
        SET
            used_days = ?,
            remaining_days = ?
        WHERE leave_balance_id = ?
    ";

    $balance_update_stmt = $conn->prepare(
        $balance_update_sql
    );

    if (!$balance_update_stmt) {
        throw new Exception("Failed to prepare balance update.");
    }

    $balance_id = (int)$balance["leave_balance_id"];

    $balance_update_stmt->bind_param(
        "iii",
        $new_used_days,
        $new_remaining_days,
        $balance_id
    );

    $balance_update_stmt->execute();


    /*
     * Confirm exactly one balance row was updated.
     */
    if ($balance_update_stmt->affected_rows !== 1) {
        throw new Exception("Leave balance could not be updated.");
    }


    /*
     * STEP 7:
     * Approve the leave request.
     */
    $approval_sql = "
        UPDATE leave_requests
        SET
            status = 'Approved',
            approved_by = ?,
            approved_at = NOW(),
            rejection_reason = NULL
        WHERE leave_request_id = ?
        AND status = 'Pending'
    ";

    $approval_stmt = $conn->prepare($approval_sql);

    if (!$approval_stmt) {
        throw new Exception("Failed to prepare leave approval.");
    }

    $approval_stmt->bind_param(
        "ii",
        $approved_by,
        $leave_request_id
    );

    $approval_stmt->execute();


    /*
     * Confirm the request was actually approved.
     */
    if ($approval_stmt->affected_rows !== 1) {
        throw new Exception("Leave request could not be approved.");
    }


    /*
     * STEP 8:
     * Commit everything.
     */
    $conn->commit();


    /*
     * Return successful approval.
     */
    echo json_encode([
        "success" => true,
        "message" => "Leave request approved successfully.",
        "data" => [
            "leave_request_id" => (int)$leave_request_id,
            "employee_id" => $employee_id,
            "leave_type_id" => $leave_type_id,
            "start_date" => $start_date,
            "end_date" => $end_date,
            "number_of_days" => $number_of_days,
            "status" => "Approved",
            "approved_by" => (int)$approved_by,
            "approved_at" => date("Y-m-d H:i:s"),
            "used_days" => $new_used_days,
            "remaining_days" => $new_remaining_days
        ]
    ]);

} catch (Exception $e) {

    /*
     * Any failure rolls back the entire operation.
     */
    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to approve leave request."
    ]);

}

?>
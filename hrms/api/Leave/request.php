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
 * Required fields.
 */
$employee_id = $data["employee_id"] ?? null;
$leave_type_id = $data["leave_type_id"] ?? null;
$start_date = $data["start_date"] ?? null;
$end_date = $data["end_date"] ?? null;
$reason = $data["reason"] ?? null;


/*
 * Validate employee ID.
 */
if (
    $employee_id === null ||
    !filter_var($employee_id, FILTER_VALIDATE_INT) ||
    $employee_id <= 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid employee ID is required."
    ]);

    exit;
}


/*
 * Validate leave type ID.
 */
if (
    $leave_type_id === null ||
    !filter_var($leave_type_id, FILTER_VALIDATE_INT) ||
    $leave_type_id <= 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid leave type ID is required."
    ]);

    exit;
}


/*
 * Validate dates exist.
 */
if (
    empty($start_date) ||
    empty($end_date)
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Start date and end date are required."
    ]);

    exit;
}


/*
 * Validate date format.
 */
$start_date_object = DateTime::createFromFormat(
    "Y-m-d",
    $start_date
);

$end_date_object = DateTime::createFromFormat(
    "Y-m-d",
    $end_date
);

if (
    !$start_date_object ||
    $start_date_object->format("Y-m-d") !== $start_date ||
    !$end_date_object ||
    $end_date_object->format("Y-m-d") !== $end_date
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Dates must use the format YYYY-MM-DD."
    ]);

    exit;
}


/*
 * Start date cannot be after end date.
 */
if ($start_date_object > $end_date_object) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Start date cannot be after end date."
    ]);

    exit;
}


/*
 * Calculate number of leave days.
 *
 * This uses inclusive calendar days.
 *
 * Example:
 * 2026-09-10 to 2026-09-12 = 3 days.
 */
$interval = $start_date_object->diff($end_date_object);

$number_of_days = $interval->days + 1;


/*
 * Validate reason length if supplied.
 */
if ($reason !== null) {

    $reason = trim($reason);

    if (strlen($reason) > 5000) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Reason is too long."
        ]);

        exit;
    }
}


try {

    /*
     * STEP 1:
     * Confirm employee exists.
     */
    $employee_sql = "
        SELECT employee_id
        FROM employees
        WHERE employee_id = ?
        LIMIT 1
    ";

    $employee_stmt = $conn->prepare($employee_sql);

    if (!$employee_stmt) {
        throw new Exception("Failed to prepare employee validation.");
    }

    $employee_stmt->bind_param(
        "i",
        $employee_id
    );

    $employee_stmt->execute();

    $employee_result = $employee_stmt->get_result();

    if ($employee_result->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Employee not found."
        ]);

        exit;
    }


    /*
     * STEP 2:
     * Confirm leave type exists and is active.
     */
    $leave_type_sql = "
        SELECT
            leave_type_id,
            leave_name,
            status
        FROM leave_types
        WHERE leave_type_id = ?
        LIMIT 1
    ";

    $leave_type_stmt = $conn->prepare($leave_type_sql);

    if (!$leave_type_stmt) {
        throw new Exception("Failed to prepare leave type validation.");
    }

    $leave_type_stmt->bind_param(
        "i",
        $leave_type_id
    );

    $leave_type_stmt->execute();

    $leave_type_result = $leave_type_stmt->get_result();

    if ($leave_type_result->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Leave type not found."
        ]);

        exit;
    }

    $leave_type = $leave_type_result->fetch_assoc();


    /*
     * Inactive leave types cannot be requested.
     */
    if ($leave_type["status"] !== "Active") {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This leave type is currently inactive."
        ]);

        exit;
    }


    /*
     * STEP 3:
     * Determine the leave year.
     *
     * The balance table is year-based.
     *
     * We require the request to stay within
     * one calendar year so it can be checked
     * against one leave balance.
     */
    $start_year = (int)$start_date_object->format("Y");
    $end_year = (int)$end_date_object->format("Y");

    if ($start_year !== $end_year) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "A leave request cannot span across two calendar years."
        ]);

        exit;
    }


    /*
     * STEP 4:
     * Retrieve the employee's leave balance.
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
    ";

    $balance_stmt = $conn->prepare($balance_sql);

    if (!$balance_stmt) {
        throw new Exception("Failed to prepare leave balance query.");
    }

    $balance_stmt->bind_param(
        "iii",
        $employee_id,
        $leave_type_id,
        $start_year
    );

    $balance_stmt->execute();

    $balance_result = $balance_stmt->get_result();

    if ($balance_result->num_rows === 0) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "No leave balance exists for this employee and leave type for the selected year."
        ]);

        exit;
    }

    $balance = $balance_result->fetch_assoc();

    $remaining_days = (int)$balance["remaining_days"];


    /*
     * STEP 5:
     * Check whether the employee has enough
     * remaining leave days.
     */
    if ($number_of_days > $remaining_days) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Insufficient leave balance.",
            "data" => [
                "requested_days" => $number_of_days,
                "remaining_days" => $remaining_days,
                "leave_name" => $leave_type["leave_name"]
            ]
        ]);

        exit;
    }


    /*
     * STEP 6:
     * Check for overlapping Pending or Approved
     * leave requests.
     *
     * Overlap condition:
     *
     * Existing start <= new end
     * AND
     * Existing end >= new start
     */
    $overlap_sql = "
        SELECT
            leave_request_id,
            start_date,
            end_date,
            status
        FROM leave_requests
        WHERE employee_id = ?
        AND leave_type_id = ?
        AND status IN ('Pending', 'Approved')
        AND start_date <= ?
        AND end_date >= ?
        LIMIT 1
    ";

    $overlap_stmt = $conn->prepare($overlap_sql);

    if (!$overlap_stmt) {
        throw new Exception("Failed to prepare leave overlap check.");
    }

    $overlap_stmt->bind_param(
        "iiss",
        $employee_id,
        $leave_type_id,
        $end_date,
        $start_date
    );

    $overlap_stmt->execute();

    $overlap_result = $overlap_stmt->get_result();

    if ($overlap_result->num_rows > 0) {

        $existing_request = $overlap_result->fetch_assoc();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This leave period overlaps with an existing leave request.",
            "data" => [
                "leave_request_id" => (int)$existing_request["leave_request_id"],
                "start_date" => $existing_request["start_date"],
                "end_date" => $existing_request["end_date"],
                "status" => $existing_request["status"]
            ]
        ]);

        exit;
    }


    /*
     * STEP 7:
     * Create the leave request.
     *
     * New requests always start as Pending.
     */
    $insert_sql = "
        INSERT INTO leave_requests
        (
            employee_id,
            leave_type_id,
            start_date,
            end_date,
            number_of_days,
            reason,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, 'Pending')
    ";

    $insert_stmt = $conn->prepare($insert_sql);

    if (!$insert_stmt) {
        throw new Exception("Failed to prepare leave request creation.");
    }

    $insert_stmt->bind_param(
        "iissis",
        $employee_id,
        $leave_type_id,
        $start_date,
        $end_date,
        $number_of_days,
        $reason
    );

    $insert_stmt->execute();

    $leave_request_id = $insert_stmt->insert_id;


    /*
     * STEP 8:
     * Return the newly created request.
     */
    echo json_encode([
        "success" => true,
        "message" => "Leave request submitted successfully.",
        "data" => [
            "leave_request_id" => (int)$leave_request_id,
            "employee_id" => (int)$employee_id,
            "leave_type_id" => (int)$leave_type_id,
            "leave_name" => $leave_type["leave_name"],
            "start_date" => $start_date,
            "end_date" => $end_date,
            "number_of_days" => $number_of_days,
            "reason" => $reason,
            "status" => "Pending"
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to submit leave request."
    ]);

}

?>
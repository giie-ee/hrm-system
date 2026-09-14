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
 * Get employee ID.
 */
$employee_id = $data["employee_id"] ?? null;

if ($user_role === "Employee") {
    $employee_id = $session_employee_id;
}


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


try {

    /*
     * STEP 1:
     * Confirm that the employee exists.
     */
    $employee_sql = "
        SELECT employee_id
        FROM employees
        WHERE employee_id = ?
        LIMIT 1
    ";

    $employee_stmt = $conn->prepare($employee_sql);

    if (!$employee_stmt) {
        throw new Exception("Failed to prepare employee validation query.");
    }

    $employee_stmt->bind_param("i", $employee_id);
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
     * Get today's date and current server time.
     */
    $attendance_date = date("Y-m-d");
    $check_out = date("H:i:s");


    /*
     * STEP 3:
     * Find today's attendance record.
     */
    $attendance_sql = "
        SELECT
            attendance_id,
            check_in,
            check_out,
            hours_worked,
            status,
            notes
        FROM attendance
        WHERE employee_id = ?
        AND attendance_date = ?
        LIMIT 1
    ";

    $attendance_stmt = $conn->prepare($attendance_sql);

    if (!$attendance_stmt) {
        throw new Exception("Failed to prepare attendance lookup.");
    }

    $attendance_stmt->bind_param(
        "is",
        $employee_id,
        $attendance_date
    );

    $attendance_stmt->execute();

    $attendance_result = $attendance_stmt->get_result();


    /*
     * No attendance record exists.
     */
    if ($attendance_result->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "No attendance record found for this employee today."
        ]);

        exit;
    }


    /*
     * Get attendance record.
     */
    $attendance = $attendance_result->fetch_assoc();

    $attendance_id = (int)$attendance["attendance_id"];


    /*
     * STEP 4:
     * Check whether the employee actually checked in.
     */
    if ($attendance["check_in"] === null) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Employee cannot check out before checking in.",
            "attendance_id" => $attendance_id
        ]);

        exit;
    }


    /*
     * STEP 5:
     * Prevent duplicate check-out.
     */
    if ($attendance["check_out"] !== null) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Employee has already checked out today.",
            "data" => [
                "attendance_id" => $attendance_id,
                "employee_id" => (int)$employee_id,
                "attendance_date" => $attendance_date,
                "check_in" => $attendance["check_in"],
                "check_out" => $attendance["check_out"],
                "hours_worked" => (float)$attendance["hours_worked"],
                "status" => $attendance["status"]
            ]
        ]);

        exit;
    }


    /*
     * STEP 6:
     * Convert check-in and check-out times into timestamps.
     */
    $check_in_time = strtotime($attendance["check_in"]);
    $check_out_time = strtotime($check_out);


    /*
     * Check-out must be later than check-in.
     */
    if ($check_out_time <= $check_in_time) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Check-out time must be later than check-in time."
        ]);

        exit;
    }


    /*
     * STEP 7:
     * Calculate hours worked.
     */
    $seconds_worked = $check_out_time - $check_in_time;

    $hours_worked = round($seconds_worked / 3600, 2);


    /*
     * STEP 8:
     * Update attendance record.
     */
    $update_sql = "
        UPDATE attendance
        SET
            check_out = ?,
            hours_worked = ?
        WHERE attendance_id = ?
    ";

    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) {
        throw new Exception("Failed to prepare check-out update.");
    }

    $update_stmt->bind_param(
        "sdi",
        $check_out,
        $hours_worked,
        $attendance_id
    );

    $update_stmt->execute();


    /*
     * STEP 9:
     * Return the completed attendance record.
     */
    echo json_encode([
        "success" => true,
        "message" => "Employee checked out successfully.",
        "data" => [
            "attendance_id" => $attendance_id,
            "employee_id" => (int)$employee_id,
            "attendance_date" => $attendance_date,
            "check_in" => $attendance["check_in"],
            "check_out" => $check_out,
            "hours_worked" => $hours_worked,
            "status" => $attendance["status"],
            "notes" => $attendance["notes"]
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to process employee check-out."
    ]);

}

?>
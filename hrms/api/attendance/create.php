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
 * Make sure valid JSON was supplied.
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
 * Get input values.
 */
$employee_id = $data["employee_id"] ?? null;
$attendance_date = $data["attendance_date"] ?? null;
$check_in = $data["check_in"] ?? null;
$check_out = $data["check_out"] ?? null;
$status = $data["status"] ?? "Present";
$notes = $data["notes"] ?? null;


/*
 * Validate required fields.
 */
if (!$employee_id || !$attendance_date) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Employee ID and attendance date are required."
    ]);

    exit;
}


/*
 * Validate employee ID.
 */
if (!filter_var($employee_id, FILTER_VALIDATE_INT) || $employee_id <= 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Employee ID must be a valid positive integer."
    ]);

    exit;
}


/*
 * Validate attendance date.
 */
$date_object = DateTime::createFromFormat("Y-m-d", $attendance_date);

if (
    !$date_object ||
    $date_object->format("Y-m-d") !== $attendance_date
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Attendance date must use the format YYYY-MM-DD."
    ]);

    exit;
}


/*
 * Allowed attendance statuses.
 *
 * These values match the database ENUM exactly.
 */
$allowed_statuses = [
    "Present",
    "Absent",
    "Late",
    "Half-Day",
    "On Leave"
];

if (!in_array($status, $allowed_statuses, true)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid attendance status.",
        "allowed_statuses" => $allowed_statuses
    ]);

    exit;
}


/*
 * Validate notes length.
 */
if ($notes !== null && strlen($notes) > 255) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Notes cannot exceed 255 characters."
    ]);

    exit;
}


/*
 * Validate time format.
 */
function isValidTime($time)
{
    if ($time === null || $time === "") {
        return true;
    }

    $time_object = DateTime::createFromFormat("H:i:s", $time);

    return $time_object &&
           $time_object->format("H:i:s") === $time;
}


/*
 * Normalise HH:MM input into HH:MM:SS.
 */
if ($check_in !== null && $check_in !== "") {

    if (preg_match("/^\d{2}:\d{2}$/", $check_in)) {
        $check_in .= ":00";
    }
}

if ($check_out !== null && $check_out !== "") {

    if (preg_match("/^\d{2}:\d{2}$/", $check_out)) {
        $check_out .= ":00";
    }
}


/*
 * Validate check-in and check-out times.
 */
if (!isValidTime($check_in)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Check-in time must use HH:MM or HH:MM:SS format."
    ]);

    exit;
}

if (!isValidTime($check_out)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Check-out time must use HH:MM or HH:MM:SS format."
    ]);

    exit;
}


/*
 * Convert empty strings to NULL.
 */
if ($check_in === "") {
    $check_in = null;
}

if ($check_out === "") {
    $check_out = null;
}


/*
 * Absent and On Leave employees should not have
 * check-in/check-out times.
 */
if (
    ($status === "Absent" || $status === "On Leave") &&
    ($check_in !== null || $check_out !== null)
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Absent or On Leave attendance cannot have check-in or check-out times."
    ]);

    exit;
}


/*
 * Check-out requires check-in.
 */
if ($check_out !== null && $check_in === null) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Check-in time is required before check-out time."
    ]);

    exit;
}


/*
 * Calculate hours worked.
 */
$hours_worked = 0.00;

if ($check_in !== null && $check_out !== null) {

    $check_in_time = strtotime($check_in);
    $check_out_time = strtotime($check_out);

    if ($check_out_time <= $check_in_time) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Check-out time must be later than check-in time."
        ]);

        exit;
    }

    $seconds_worked = $check_out_time - $check_in_time;

    $hours_worked = round($seconds_worked / 3600, 2);
}


/*
 * Database operations.
 */
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
     * Prevent duplicate attendance for the same
     * employee and date.
     */
    $duplicate_sql = "
        SELECT attendance_id
        FROM attendance
        WHERE employee_id = ?
        AND attendance_date = ?
        LIMIT 1
    ";

    $duplicate_stmt = $conn->prepare($duplicate_sql);

    if (!$duplicate_stmt) {
        throw new Exception("Failed to prepare duplicate attendance check.");
    }

    $duplicate_stmt->bind_param(
        "is",
        $employee_id,
        $attendance_date
    );

    $duplicate_stmt->execute();

    $duplicate_result = $duplicate_stmt->get_result();

    if ($duplicate_result->num_rows > 0) {

        $existing = $duplicate_result->fetch_assoc();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Attendance already exists for this employee on this date.",
            "attendance_id" => (int)$existing["attendance_id"]
        ]);

        exit;
    }


    /*
     * STEP 3:
     * Insert attendance record.
     */
    $insert_sql = "
        INSERT INTO attendance
        (
            employee_id,
            attendance_date,
            check_in,
            check_out,
            hours_worked,
            status,
            notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $insert_stmt = $conn->prepare($insert_sql);

    if (!$insert_stmt) {
        throw new Exception("Failed to prepare attendance insert query.");
    }

    $insert_stmt->bind_param(
        "isssdss",
        $employee_id,
        $attendance_date,
        $check_in,
        $check_out,
        $hours_worked,
        $status,
        $notes
    );

    $insert_stmt->execute();

    $attendance_id = $insert_stmt->insert_id;


    /*
     * STEP 4:
     * Return the created attendance record.
     */
    echo json_encode([
        "success" => true,
        "message" => "Attendance record created successfully.",
        "data" => [
            "attendance_id" => (int)$attendance_id,
            "employee_id" => (int)$employee_id,
            "attendance_date" => $attendance_date,
            "check_in" => $check_in,
            "check_out" => $check_out,
            "hours_worked" => $hours_worked,
            "status" => $status,
            "notes" => $notes
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create attendance record."
    ]);

}

?>
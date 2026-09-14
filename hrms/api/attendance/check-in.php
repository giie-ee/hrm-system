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
     * Get today's date and current time.
     *
     * The timezone is configured centrally in database.php.
     */
    $attendance_date = date("Y-m-d");
    $check_in = date("H:i:s");


    /*
     * STEP 3:
     * Check whether an attendance record already
     * exists for this employee today.
     */
    $attendance_sql = "
        SELECT
            attendance_id,
            check_in,
            check_out,
            status,
            hours_worked
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
     * STEP 4:
     * If today's attendance already exists,
     * handle it safely.
     */
    if ($attendance_result->num_rows > 0) {

        $attendance = $attendance_result->fetch_assoc();

        $attendance_id = (int)$attendance["attendance_id"];


        /*
         * Employee has already checked in.
         */
        if ($attendance["check_in"] !== null) {

            http_response_code(409);

            echo json_encode([
                "success" => false,
                "message" => "Employee has already checked in today.",
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
         * An employee marked On Leave cannot check in.
         */
        if ($attendance["status"] === "On Leave") {

            http_response_code(409);

            echo json_encode([
                "success" => false,
                "message" => "Employee is marked as On Leave today and cannot check in.",
                "attendance_id" => $attendance_id
            ]);

            exit;
        }


        /*
         * An attendance record exists but has no check-in.
         *
         * Update that existing record instead of
         * creating a duplicate.
         */
        $update_sql = "
            UPDATE attendance
            SET
                check_in = ?,
                status = 'Present'
            WHERE attendance_id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);

        if (!$update_stmt) {
            throw new Exception("Failed to prepare check-in update.");
        }

        $update_stmt->bind_param(
            "si",
            $check_in,
            $attendance_id
        );

        $update_stmt->execute();


        /*
         * Return updated attendance record.
         */
        echo json_encode([
            "success" => true,
            "message" => "Employee checked in successfully.",
            "data" => [
                "attendance_id" => $attendance_id,
                "employee_id" => (int)$employee_id,
                "attendance_date" => $attendance_date,
                "check_in" => $check_in,
                "check_out" => $attendance["check_out"],
                "hours_worked" => (float)$attendance["hours_worked"],
                "status" => "Present"
            ]
        ]);

        exit;
    }


    /*
     * STEP 5:
     * No attendance record exists today.
     *
     * Create one.
     */
    $insert_sql = "
        INSERT INTO attendance
        (
            employee_id,
            attendance_date,
            check_in,
            check_out,
            hours_worked,
            status
        )
        VALUES (?, ?, ?, NULL, 0.00, 'Present')
    ";

    $insert_stmt = $conn->prepare($insert_sql);

    if (!$insert_stmt) {
        throw new Exception("Failed to prepare attendance creation.");
    }

    $insert_stmt->bind_param(
        "iss",
        $employee_id,
        $attendance_date,
        $check_in
    );

    $insert_stmt->execute();

    $attendance_id = $insert_stmt->insert_id;


    /*
     * Return newly created attendance record.
     */
    echo json_encode([
        "success" => true,
        "message" => "Employee checked in successfully.",
        "data" => [
            "attendance_id" => (int)$attendance_id,
            "employee_id" => (int)$employee_id,
            "attendance_date" => $attendance_date,
            "check_in" => $check_in,
            "check_out" => null,
            "hours_worked" => 0.00,
            "status" => "Present"
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to process employee check-in."
    ]);

}

?>
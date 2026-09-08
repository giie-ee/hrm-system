<?php

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();


/*
 * Only GET requests are allowed.
 */
if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);

    exit;
}


/*
 * Optional filters.
 *
 * employee_id:
 *     /get.php?employee_id=2
 *
 * date:
 *     /get.php?date=2026-09-05
 *
 * status:
 *     /get.php?status=Present
 */
$employee_id = $_GET["employee_id"] ?? null;
$date = $_GET["date"] ?? null;
$status = $_GET["status"] ?? null;


/*
 * Validate employee_id if supplied.
 */
if ($employee_id !== null) {

    if (
        !filter_var($employee_id, FILTER_VALIDATE_INT) ||
        $employee_id <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Employee ID must be a valid positive integer."
        ]);

        exit;
    }
}


/*
 * Validate date if supplied.
 */
if ($date !== null) {

    $date_object = DateTime::createFromFormat("Y-m-d", $date);

    if (
        !$date_object ||
        $date_object->format("Y-m-d") !== $date
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Date must use the format YYYY-MM-DD."
        ]);

        exit;
    }
}


/*
 * Validate status if supplied.
 */
if ($status !== null) {

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
}


try {

    /*
     * Base query.
     *
     * We join employees so the frontend can receive
     * employee information without making another request.
     */
    $sql = "
        SELECT
            a.attendance_id,
            a.employee_id,
            CONCAT(
                e.first_name,
                ' ',
                e.last_name
            ) AS employee_name,
            a.attendance_date,
            a.check_in,
            a.check_out,
            a.hours_worked,
            a.status,
            a.notes,
            a.created_at,
            a.updated_at
        FROM attendance a
        INNER JOIN employees e
            ON a.employee_id = e.employee_id
    ";


    /*
     * Build filters dynamically.
     */
    $conditions = [];
    $parameters = [];
    $types = "";


    if ($employee_id !== null) {

        $conditions[] = "a.employee_id = ?";
        $parameters[] = (int)$employee_id;
        $types .= "i";
    }


    if ($date !== null) {

        $conditions[] = "a.attendance_date = ?";
        $parameters[] = $date;
        $types .= "s";
    }


    if ($status !== null) {

        $conditions[] = "a.status = ?";
        $parameters[] = $status;
        $types .= "s";
    }


    /*
     * Add WHERE clause if filters exist.
     */
    if (count($conditions) > 0) {

        $sql .= " WHERE " . implode(" AND ", $conditions);
    }


    /*
     * Most recent attendance first.
     */
    $sql .= "
        ORDER BY
            a.attendance_date DESC,
            a.attendance_id DESC
    ";


    /*
     * Prepare query.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare attendance query.");
    }


    /*
     * Bind parameters when filters are supplied.
     *
     * call_user_func_array allows us to bind a
     * dynamically generated number of parameters.
     */
    if (count($parameters) > 0) {

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


    /*
     * Execute query.
     */
    $stmt->execute();

    $result = $stmt->get_result();


    /*
     * Build response data.
     */
    $attendance_records = [];

    while ($row = $result->fetch_assoc()) {

        $attendance_records[] = [
            "attendance_id" => (int)$row["attendance_id"],
            "employee_id" => (int)$row["employee_id"],
            "employee_name" => $row["employee_name"],
            "attendance_date" => $row["attendance_date"],
            "check_in" => $row["check_in"],
            "check_out" => $row["check_out"],
            "hours_worked" => (float)$row["hours_worked"],
            "status" => $row["status"],
            "notes" => $row["notes"],
            "created_at" => $row["created_at"],
            "updated_at" => $row["updated_at"]
        ];
    }


    /*
     * Return successful response.
     */
    echo json_encode([
        "success" => true,
        "message" => "Attendance records retrieved successfully.",
        "count" => count($attendance_records),
        "data" => $attendance_records
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve attendance records."
    ]);

}

?>
<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * ==========================================================
 * MANAGER ATTENDANCE API
 * ==========================================================
 *
 * Allows an authenticated Manager to view attendance
 * records belonging ONLY to employees currently assigned
 * to that Manager.
 *
 * The Manager's employee ID comes from the authenticated
 * session.
 *
 * An employee_id filter may optionally be supplied, but
 * the database query still requires an ACTIVE manager
 * assignment before returning the record.
 */


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
 * Only Managers may access this endpoint.
 */
requireRole(["Manager"]);


/*
 * Get authenticated Manager employee ID.
 */
$manager_employee_id = getCurrentEmployeeId();


/*
 * Validate Manager employee ID.
 */
if (
    $manager_employee_id === null ||
    !filter_var(
        $manager_employee_id,
        FILTER_VALIDATE_INT
    ) ||
    (int)$manager_employee_id <= 0
) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Manager employee profile not found."
    ]);

    exit;
}


$manager_employee_id = (int)$manager_employee_id;


/*
 * ==========================================================
 * OPTIONAL FILTERS
 * ==========================================================
 *
 * employee_id
 * date
 * month
 * status
 */


/*
 * Optional employee filter.
 */
$employee_id = $_GET["employee_id"] ?? null;

if ($employee_id !== null) {

    if (
        !filter_var(
            $employee_id,
            FILTER_VALIDATE_INT
        ) ||
        (int)$employee_id <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid employee ID."
        ]);

        exit;
    }

    $employee_id = (int)$employee_id;
}


/*
 * Optional exact date filter.
 *
 * Example:
 * ?date=2026-09-13
 */
$date = $_GET["date"] ?? null;

if ($date !== null) {

    $date_object = DateTime::createFromFormat(
        "Y-m-d",
        $date
    );

    if (
        !$date_object ||
        $date_object->format("Y-m-d") !== $date
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid date. Use YYYY-MM-DD."
        ]);

        exit;
    }
}


/*
 * Optional month filter.
 *
 * Example:
 * ?month=2026-09
 */
$month = $_GET["month"] ?? null;

if ($month !== null) {

    $month_object = DateTime::createFromFormat(
        "Y-m",
        $month
    );

    if (
        !$month_object ||
        $month_object->format("Y-m") !== $month
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid month. Use YYYY-MM."
        ]);

        exit;
    }
}


/*
 * Do not allow date and month simultaneously.
 */
if ($date !== null && $month !== null) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Use either date or month, not both."
    ]);

    exit;
}


/*
 * Optional attendance status.
 */
$status = $_GET["status"] ?? null;


$allowed_statuses = [
    "Present",
    "Absent",
    "Late",
    "Half-Day",
    "On Leave"
];


if ($status !== null) {

    $status = trim($status);

    if (
        !in_array(
            $status,
            $allowed_statuses,
            true
        )
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid attendance status."
        ]);

        exit;
    }
}


try {

    /*
     * ==========================================================
     * BUILD ATTENDANCE QUERY
     * ==========================================================
     *
     * The critical security condition is:
     *
     * ma.manager_employee_id = authenticated manager
     *
     * AND
     *
     * ma.status = 'Active'
     *
     * This prevents managers from accessing employees who
     * are not assigned to them.
     */
    $sql = "
        SELECT
            a.attendance_id,
            a.employee_id,
            a.attendance_date,
            a.check_in,
            a.check_out,
            a.hours_worked,
            a.status,
            a.notes,

            e.employee_number,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.email,

            d.department_name,
            p.position_name

        FROM attendance a

        INNER JOIN employees e
            ON e.employee_id = a.employee_id

        INNER JOIN manager_assignments ma
            ON ma.employee_id = a.employee_id
           AND ma.manager_employee_id = ?
           AND ma.status = 'Active'

        LEFT JOIN departments d
            ON d.department_id = e.department_id

        LEFT JOIN positions p
            ON p.position_id = e.position_id

        WHERE 1 = 1
    ";


    $params = [
        $manager_employee_id
    ];

    $types = "i";


    /*
     * Optional employee filter.
     */
    if ($employee_id !== null) {

        $sql .= "
            AND a.employee_id = ?
        ";

        $params[] = $employee_id;
        $types .= "i";
    }


    /*
     * Exact date filter.
     */
    if ($date !== null) {

        $sql .= "
            AND a.attendance_date = ?
        ";

        $params[] = $date;
        $types .= "s";
    }


    /*
     * Month filter.
     */
    if ($month !== null) {

        $sql .= "
            AND TO_CHAR(a.attendance_date, 'YYYY-MM') = ?
        ";

        $params[] = $month;
        $types .= "s";
    }


    /*
     * Attendance status filter.
     */
    if ($status !== null) {

        $sql .= "
            AND a.status = ?
        ";

        $params[] = $status;
        $types .= "s";
    }


    /*
     * Newest attendance first.
     */
    $sql .= "
        ORDER BY
            a.attendance_date DESC,
            e.first_name ASC,
            e.last_name ASC,
            a.attendance_id DESC
    ";


    /*
     * Prepare query.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Failed to prepare manager attendance query."
        );
    }


    /*
     * Bind dynamic parameters.
     */
    $stmt->bind_param(
        $types,
        ...$params
    );


    /*
     * Execute.
     */
    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to retrieve attendance records."
        );
    }


    $result = $stmt->get_result();

    $records = [];


    /*
     * ==========================================================
     * SUMMARY COUNTERS
     * ==========================================================
     */
    $summary = [
        "total_records" => 0,
        "present" => 0,
        "absent" => 0,
        "late" => 0,
        "half_day" => 0,
        "on_leave" => 0,
        "total_hours_worked" => 0
    ];


    /*
     * ==========================================================
     * BUILD RESPONSE
     * ==========================================================
     */
    while ($attendance = $result->fetch_assoc()) {

        $full_name = trim(
            $attendance["first_name"] .
            " " .
            (
                $attendance["middle_name"] !== null
                    ? $attendance["middle_name"] . " "
                    : ""
            ) .
            $attendance["last_name"]
        );


        $records[] = [

            "attendance_id" =>
                (int)$attendance["attendance_id"],

            "employee" => [

                "employee_id" =>
                    (int)$attendance["employee_id"],

                "employee_number" =>
                    $attendance["employee_number"],

                "full_name" =>
                    $full_name,

                "email" =>
                    $attendance["email"]
            ],

            "department" =>
                $attendance["department_name"],

            "position" =>
                $attendance["position_name"],

            "attendance" => [

                "date" =>
                    $attendance["attendance_date"],

                "check_in" =>
                    $attendance["check_in"],

                "check_out" =>
                    $attendance["check_out"],

                "hours_worked" =>
                    (float)$attendance["hours_worked"],

                "status" =>
                    $attendance["status"],

                "notes" =>
                    $attendance["notes"]
            ]
        ];


        /*
         * Update summary.
         */
        $summary["total_records"]++;

        $summary["total_hours_worked"] +=
            (float)$attendance["hours_worked"];


        switch ($attendance["status"]) {

            case "Present":
                $summary["present"]++;
                break;

            case "Absent":
                $summary["absent"]++;
                break;

            case "Late":
                $summary["late"]++;
                break;

            case "Half-Day":
                $summary["half_day"]++;
                break;

            case "On Leave":
                $summary["on_leave"]++;
                break;
        }
    }


    $stmt->close();


    /*
     * Round total hours for cleaner API output.
     */
    $summary["total_hours_worked"] =
        round(
            $summary["total_hours_worked"],
            2
        );


    /*
     * ==========================================================
     * RESPONSE
     * ==========================================================
     */
    echo json_encode([

        "success" => true,

        "message" =>
            "Team attendance retrieved successfully.",

        "data" => [

            "manager_employee_id" =>
                $manager_employee_id,

            "filters" => [

                "employee_id" =>
                    $employee_id,

                "date" =>
                    $date,

                "month" =>
                    $month,

                "status" =>
                    $status
            ],

            "summary" =>
                $summary,

            "records" =>
                $records
        ]
    ]);

} catch (Exception $e) {
    if ($e instanceof ApiError) throw $e;

    /*
     * Never expose internal database errors.
     */
    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" =>
            "Unable to retrieve team attendance."
    ]);
}

?>

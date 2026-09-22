<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * ==========================================================
 * MANAGER LEAVE REVIEW API
 * ==========================================================
 *
 * Returns leave requests belonging ONLY to employees
 * assigned to the authenticated Manager.
 *
 * Managers can use this endpoint to review their team's
 * leave requests.
 *
 * IMPORTANT:
 *
 * This endpoint is READ-ONLY.
 *
 * Actual approval and rejection continue to use:
 *
 *     api/leave/approve.php
 *     api/leave/reject.php
 *
 * Those endpoints already contain the transactional
 * business rules for changing leave status and balances.
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
 * Only Managers can access this endpoint.
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
 * status
 * year
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
 * Optional leave status filter.
 */
$status = $_GET["status"] ?? null;


$allowed_statuses = [
    "Pending",
    "Approved",
    "Rejected",
    "Cancelled"
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
            "message" => "Invalid leave request status."
        ]);

        exit;
    }
}


/*
 * Optional year filter.
 */
$year = $_GET["year"] ?? null;


if ($year !== null) {

    if (
        !filter_var(
            $year,
            FILTER_VALIDATE_INT
        ) ||
        (int)$year < 2000 ||
        (int)$year > 2100
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid year."
        ]);

        exit;
    }

    $year = (int)$year;
}


try {

    /*
     * ==========================================================
     * BUILD QUERY
     * ==========================================================
     *
     * The manager_assignments table is the authorization
     * boundary.
     *
     * Only employees with an ACTIVE assignment to the
     * authenticated Manager are included.
     */
    $sql = "
        SELECT

            lr.leave_request_id,
            lr.employee_id,
            lr.leave_type_id,
            lr.start_date,
            lr.end_date,
            lr.number_of_days,
            lr.reason,
            lr.status,
            lr.approved_by,
            lr.approved_at,
            lr.rejection_reason,
            lr.created_at,
            lr.updated_at,

            e.employee_number,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.email,

            d.department_name,

            p.position_name,

            lt.leave_name,
            lt.description AS leave_description

        FROM leave_requests lr

        INNER JOIN employees e
            ON e.employee_id = lr.employee_id

        INNER JOIN manager_assignments ma
            ON ma.employee_id = lr.employee_id
           AND ma.manager_employee_id = ?
           AND ma.status = 'Active'

        INNER JOIN leave_types lt
            ON lt.leave_type_id = lr.leave_type_id

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
            AND lr.employee_id = ?
        ";

        $params[] = $employee_id;
        $types .= "i";
    }


    /*
     * Optional status filter.
     */
    if ($status !== null) {

        $sql .= "
            AND lr.status = ?
        ";

        $params[] = $status;
        $types .= "s";
    }


    /*
     * Optional year filter.
     *
     * A request is included if its start date
     * falls within the selected year.
     */
    if ($year !== null) {

        $sql .= "
            AND EXTRACT(YEAR FROM lr.start_date) = ?
        ";

        $params[] = $year;
        $types .= "i";
    }


    /*
     * Pending requests first.
     *
     * Then newest requests.
     */
    $sql .= "
        ORDER BY
            CASE
                WHEN lr.status = 'Pending' THEN 0
                ELSE 1
            END,
            lr.created_at DESC,
            lr.leave_request_id DESC
    ";


    /*
     * Prepare query.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Failed to prepare manager leave query."
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
            "Failed to retrieve team leave requests."
        );
    }


    $result = $stmt->get_result();

    $requests = [];


    /*
     * ==========================================================
     * SUMMARY
     * ==========================================================
     */
    $summary = [

        "total_requests" => 0,

        "pending" => 0,

        "approved" => 0,

        "rejected" => 0,

        "cancelled" => 0,

        "pending_days" => 0
    ];


    /*
     * ==========================================================
     * BUILD REQUEST DATA
     * ==========================================================
     */
    while ($request = $result->fetch_assoc()) {

        /*
         * Build employee full name.
         */
        $full_name = trim(

            $request["first_name"] .

            " " .

            (
                $request["middle_name"] !== null
                    ? $request["middle_name"] . " "
                    : ""
            ) .

            $request["last_name"]
        );


        /*
         * Build request object.
         */
        $requests[] = [

            "leave_request_id" =>
                (int)$request["leave_request_id"],

            "employee" => [

                "employee_id" =>
                    (int)$request["employee_id"],

                "employee_number" =>
                    $request["employee_number"],

                "full_name" =>
                    $full_name,

                "email" =>
                    $request["email"]
            ],

            "department" =>
                $request["department_name"],

            "position" =>
                $request["position_name"],

            "leave" => [

                "leave_type_id" =>
                    (int)$request["leave_type_id"],

                "leave_name" =>
                    $request["leave_name"],

                "description" =>
                    $request["leave_description"],

                "start_date" =>
                    $request["start_date"],

                "end_date" =>
                    $request["end_date"],

                "number_of_days" =>
                    (float)$request["number_of_days"],

                "reason" =>
                    $request["reason"]
            ],

            "status" =>
                $request["status"],

            "review" => [

                "reviewed_by" =>
                    $request["approved_by"] !== null
                        ? (int)$request["approved_by"]
                        : null,

                "reviewed_at" =>
                    $request["approved_at"],

                "rejection_reason" =>
                    $request["rejection_reason"]
            ],

            "created_at" =>
                $request["created_at"],

            "updated_at" =>
                $request["updated_at"]
        ];


        /*
         * Update summary counters.
         */
        $summary["total_requests"]++;


        switch ($request["status"]) {

            case "Pending":

                $summary["pending"]++;

                $summary["pending_days"] +=
                    (float)$request["number_of_days"];

                break;


            case "Approved":

                $summary["approved"]++;

                break;


            case "Rejected":

                $summary["rejected"]++;

                break;


            case "Cancelled":

                $summary["cancelled"]++;

                break;
        }
    }


    $stmt->close();


    /*
     * Round pending days.
     */
    $summary["pending_days"] =
        round(
            $summary["pending_days"],
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
            "Team leave requests retrieved successfully.",

        "data" => [

            "manager_employee_id" =>
                $manager_employee_id,

            "filters" => [

                "employee_id" =>
                    $employee_id,

                "status" =>
                    $status,

                "year" =>
                    $year
            ],

            "summary" =>
                $summary,

            "requests" =>
                $requests
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
            "Unable to retrieve team leave requests."
    ]);
}

?>

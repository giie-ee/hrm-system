<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * Self-Service Leave API
 *
 * Allows the authenticated employee to view:
 *
 * 1. Their leave balances
 * 2. Their leave request history
 *
 * The employee ID is obtained from the authenticated
 * session and is NEVER accepted from the client.
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
 * Get authenticated employee ID.
 */
$employee_id = getCurrentEmployeeId();


/*
 * Validate authenticated employee ID.
 */
if (
    $employee_id === null ||
    !filter_var($employee_id, FILTER_VALIDATE_INT) ||
    (int)$employee_id <= 0
) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Authenticated employee profile not found."
    ]);

    exit;
}


$employee_id = (int)$employee_id;


/*
 * Optional filters.
 *
 * year:
 * Filters leave balances and requests by year.
 *
 * status:
 * Filters leave requests by status.
 */
$year = $_GET["year"] ?? date("Y");
$status = $_GET["status"] ?? null;


/*
 * Validate year.
 */
if (
    !filter_var($year, FILTER_VALIDATE_INT) ||
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


/*
 * Allowed leave request statuses.
 */
$allowed_statuses = [
    "Pending",
    "Approved",
    "Rejected",
    "Cancelled"
];


/*
 * Validate optional status.
 */
if ($status !== null) {

    $status = trim($status);

    if (!in_array($status, $allowed_statuses, true)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid leave request status."
        ]);

        exit;
    }
}


try {

    /*
     * ==========================================================
     * STEP 1: Retrieve leave balances
     * ==========================================================
     *
     * Only balances belonging to the authenticated employee
     * are returned.
     */
    $balance_sql = "
        SELECT
            lb.leave_balance_id,
            lb.employee_id,
            lb.leave_type_id,
            lt.leave_name,
            lt.description,
            lb.total_days,
            lb.used_days,
            lb.remaining_days,
            lb.year

        FROM leave_balances lb

        INNER JOIN leave_types lt
            ON lt.leave_type_id = lb.leave_type_id

        WHERE lb.employee_id = ?
          AND lb.year = ?

        ORDER BY lt.leave_name ASC
    ";


    $balance_stmt = $conn->prepare($balance_sql);

    if (!$balance_stmt) {

        throw new Exception("Failed to prepare leave balance query.");
    }


    $balance_stmt->bind_param(
        "ii",
        $employee_id,
        $year
    );


    if (!$balance_stmt->execute()) {

        throw new Exception("Failed to retrieve leave balances.");
    }


    $balance_result = $balance_stmt->get_result();

    $balances = [];


    while ($balance = $balance_result->fetch_assoc()) {

        $balances[] = [
            "leave_balance_id" => (int)$balance["leave_balance_id"],
            "leave_type_id" => (int)$balance["leave_type_id"],
            "leave_name" => $balance["leave_name"],
            "description" => $balance["description"],
            "total_days" => (float)$balance["total_days"],
            "used_days" => (float)$balance["used_days"],
            "remaining_days" => (float)$balance["remaining_days"],
            "year" => (int)$balance["year"]
        ];
    }


    $balance_stmt->close();


    /*
     * ==========================================================
     * STEP 2: Retrieve leave request history
     * ==========================================================
     *
     * Again, the employee ID comes exclusively from the
     * authenticated session.
     */
    $request_sql = "
        SELECT
            lr.leave_request_id,
            lr.employee_id,
            lr.leave_type_id,
            lt.leave_name,
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

            reviewer.username AS reviewer_username,

            reviewer_employee.first_name AS reviewer_first_name,
            reviewer_employee.last_name AS reviewer_last_name

        FROM leave_requests lr

        INNER JOIN leave_types lt
            ON lt.leave_type_id = lr.leave_type_id

        LEFT JOIN users reviewer
            ON reviewer.user_id = lr.approved_by

        LEFT JOIN employees reviewer_employee
            ON reviewer_employee.employee_id = reviewer.employee_id

        WHERE lr.employee_id = ?

          AND (
                EXTRACT(YEAR FROM lr.start_date) = ?
                OR EXTRACT(YEAR FROM lr.end_date) = ?
              )
    ";


    /*
     * Add optional status filter.
     */
    if ($status !== null) {

        $request_sql .= "
            AND lr.status = ?
        ";
    }


    /*
     * Newest requests first.
     */
    $request_sql .= "
        ORDER BY lr.created_at DESC, lr.leave_request_id DESC
    ";


    $request_stmt = $conn->prepare($request_sql);

    if (!$request_stmt) {

        throw new Exception("Failed to prepare leave request query.");
    }


    /*
     * Bind parameters depending on whether
     * a status filter was supplied.
     */
    if ($status !== null) {

        $request_stmt->bind_param(
            "iiis",
            $employee_id,
            $year,
            $year,
            $status
        );

    } else {

        $request_stmt->bind_param(
            "iii",
            $employee_id,
            $year,
            $year
        );
    }


    if (!$request_stmt->execute()) {

        throw new Exception("Failed to retrieve leave requests.");
    }


    $request_result = $request_stmt->get_result();

    $requests = [];


    while ($request = $request_result->fetch_assoc()) {

        $reviewer_name = null;


        /*
         * Build reviewer name when available.
         */
        if (
            $request["reviewer_first_name"] !== null ||
            $request["reviewer_last_name"] !== null
        ) {

            $reviewer_name = trim(
                ($request["reviewer_first_name"] ?? "") .
                " " .
                ($request["reviewer_last_name"] ?? "")
            );
        }


        $requests[] = [
            "leave_request_id" => (int)$request["leave_request_id"],
            "leave_type_id" => (int)$request["leave_type_id"],
            "leave_name" => $request["leave_name"],
            "start_date" => $request["start_date"],
            "end_date" => $request["end_date"],
            "number_of_days" => (float)$request["number_of_days"],
            "reason" => $request["reason"],
            "status" => $request["status"],

            "review" => [
                "reviewed_by" => $request["approved_by"] !== null
                    ? (int)$request["approved_by"]
                    : null,

                "reviewer_name" => $reviewer_name,

                "reviewer_username" => $request["reviewer_username"],

                "reviewed_at" => $request["approved_at"],

                "rejection_reason" => $request["rejection_reason"]
            ],

            "created_at" => $request["created_at"],
            "updated_at" => $request["updated_at"]
        ];
    }


    $request_stmt->close();


    /*
     * ==========================================================
     * STEP 3: Calculate summary information
     * ==========================================================
     */
    $summary = [
        "total_requests" => count($requests),
        "pending" => 0,
        "approved" => 0,
        "rejected" => 0,
        "cancelled" => 0,
        "total_approved_days" => 0
    ];


    foreach ($requests as $request) {

        switch ($request["status"]) {

            case "Pending":
                $summary["pending"]++;
                break;

            case "Approved":
                $summary["approved"]++;
                $summary["total_approved_days"] +=
                    $request["number_of_days"];
                break;

            case "Rejected":
                $summary["rejected"]++;
                break;

            case "Cancelled":
                $summary["cancelled"]++;
                break;
        }
    }


    /*
     * ==========================================================
     * STEP 4: Return response
     * ==========================================================
     */
    echo json_encode([
        "success" => true,
        "message" => "Leave information retrieved successfully.",

        "data" => [
            "employee_id" => $employee_id,

            "year" => $year,

            "summary" => $summary,

            "balances" => $balances,

            "requests" => $requests
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
        "message" => "Unable to retrieve leave information."
    ]);
}

?>

<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * ==========================================================
 * MANAGER TEAM API
 * ==========================================================
 *
 * Returns the employees assigned to the authenticated manager.
 *
 * IMPORTANT:
 *
 * The manager employee ID is obtained from the authenticated
 * session.
 *
 * The client cannot provide a manager_employee_id to access
 * another manager's team.
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
 * Get the authenticated manager's employee ID.
 */
$manager_employee_id = getCurrentEmployeeId();


/*
 * Validate manager employee ID.
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
 * Optional employee status filter.
 *
 * Example:
 *
 * ?status=Active
 *
 * This filters the employee's employment status,
 * not the manager assignment status.
 */
$status = $_GET["status"] ?? null;


$allowed_statuses = [
    "Active",
    "Inactive",
    "Suspended",
    "Terminated"
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
            "message" => "Invalid employee status."
        ]);

        exit;
    }
}


try {

    /*
     * ==========================================================
     * STEP 1
     * Retrieve manager's assigned employees.
     * ==========================================================
     *
     * The manager_assignments table is the authorization
     * boundary.
     *
     * Only Active assignments are returned.
     */
    $sql = "
        SELECT
            ma.assignment_id,
            ma.manager_employee_id,
            ma.employee_id,
            ma.assigned_at,

            e.employee_number,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.gender,
            e.email,
            e.phone,
            e.address,
            e.employment_type,
            e.hire_date,
            e.employment_status,

            d.department_id,
            d.department_name,

            p.position_id,
            p.position_name

        FROM manager_assignments ma

        INNER JOIN employees e
            ON e.employee_id = ma.employee_id

        LEFT JOIN departments d
            ON d.department_id = e.department_id

        LEFT JOIN positions p
            ON p.position_id = e.position_id

        WHERE ma.manager_employee_id = ?
          AND ma.status = 'Active'
    ";


    /*
     * Optional employee employment-status filter.
     */
    if ($status !== null) {

        $sql .= "
            AND e.employment_status = ?
        ";
    }


    /*
     * Order employees consistently.
     */
    $sql .= "
        ORDER BY
            e.first_name ASC,
            e.last_name ASC,
            e.employee_id ASC
    ";


    /*
     * Prepare statement.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Failed to prepare team query."
        );
    }


    /*
     * Bind parameters.
     */
    if ($status !== null) {

        $stmt->bind_param(
            "is",
            $manager_employee_id,
            $status
        );

    } else {

        $stmt->bind_param(
            "i",
            $manager_employee_id
        );
    }


    /*
     * Execute query.
     */
    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to retrieve team members."
        );
    }


    $result = $stmt->get_result();

    $employees = [];


    /*
     * ==========================================================
     * STEP 2
     * Build employee records.
     * ==========================================================
     */
    while ($employee = $result->fetch_assoc()) {

        $full_name = trim(
            $employee["first_name"] .
            " " .
            ($employee["middle_name"] !== null
                ? $employee["middle_name"] . " "
                : "") .
            $employee["last_name"]
        );


        $employees[] = [

            "assignment_id" =>
                (int)$employee["assignment_id"],

            "employee" => [

                "employee_id" =>
                    (int)$employee["employee_id"],

                "employee_number" =>
                    $employee["employee_number"],

                "full_name" =>
                    $full_name,

                "first_name" =>
                    $employee["first_name"],

                "middle_name" =>
                    $employee["middle_name"],

                "last_name" =>
                    $employee["last_name"],

                "gender" =>
                    $employee["gender"],

                "email" =>
                    $employee["email"],

                "phone" =>
                    $employee["phone"],

                "address" =>
                    $employee["address"],

                "employment_type" =>
                    $employee["employment_type"],

                "hire_date" =>
                    $employee["hire_date"],

                "employment_status" =>
                    $employee["employment_status"]
            ],

            "department" => [

                "department_id" =>
                    $employee["department_id"] !== null
                        ? (int)$employee["department_id"]
                        : null,

                "department_name" =>
                    $employee["department_name"]
            ],

            "position" => [

                "position_id" =>
                    $employee["position_id"] !== null
                        ? (int)$employee["position_id"]
                        : null,

                "position_name" =>
                    $employee["position_name"]
            ],

            "assigned_at" =>
                $employee["assigned_at"]
        ];
    }


    $stmt->close();


    /*
     * ==========================================================
     * STEP 3
     * Build team summary.
     * ==========================================================
     */
    $summary = [

        "total_assigned" =>
            count($employees),

        "active" => 0,

        "inactive" => 0,

        "suspended" => 0,

        "terminated" => 0
    ];


    foreach ($employees as $employee) {

        switch (
            $employee["employee"]["employment_status"]
        ) {

            case "Active":
                $summary["active"]++;
                break;

            case "Inactive":
                $summary["inactive"]++;
                break;

            case "Suspended":
                $summary["suspended"]++;
                break;

            case "Terminated":
                $summary["terminated"]++;
                break;
        }
    }


    /*
     * ==========================================================
     * STEP 4
     * Return response.
     * ==========================================================
     */
    echo json_encode([

        "success" => true,

        "message" =>
            "Team information retrieved successfully.",

        "data" => [

            "manager_employee_id" =>
                $manager_employee_id,

            "summary" =>
                $summary,

            "employees" =>
                $employees
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
            "Unable to retrieve team information."
    ]);
}

?>
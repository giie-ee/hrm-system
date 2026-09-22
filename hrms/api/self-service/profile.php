<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * This endpoint is for viewing the currently
 * authenticated employee's profile.
 *
 * The employee_id is taken from the PHP session.
 * The client must NOT supply an employee_id.
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
 * Get the employee ID from the authenticated session.
 */
$employee_id = getCurrentEmployeeId();


/*
 * Validate employee ID.
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


try {

    /*
     * Retrieve the employee's profile.
     *
     * We deliberately do not accept employee_id
     * from the request URL or query string.
     *
     * This prevents an employee from attempting:
     *
     * ?employee_id=5
     *
     * to access somebody else's profile.
     */
    $sql = "
        SELECT
            e.employee_id,
            e.employee_number,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.gender,
            e.date_of_birth,
            e.national_id,
            e.email,
            e.phone,
            e.address,
            e.department_id,
            e.position_id,
            e.employment_type,
            e.hire_date,
            e.employment_status,
            e.created_at,
            e.updated_at,

            u.user_id,
            u.username,
            u.account_status,
            u.last_login,

            r.role_id,
            r.role_name

        FROM employees e

        LEFT JOIN users u
            ON u.employee_id = e.employee_id

        LEFT JOIN roles r
            ON r.role_id = u.role_id

        WHERE e.employee_id = ?

        LIMIT 1
    ";


    /*
     * Prepare statement.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception("Failed to prepare profile query.");
    }


    /*
     * Bind authenticated employee ID.
     */
    $stmt->bind_param(
        "i",
        $employee_id
    );


    /*
     * Execute query.
     */
    if (!$stmt->execute()) {

        throw new Exception("Failed to retrieve employee profile.");
    }


    /*
     * Retrieve result.
     */
    $result = $stmt->get_result();


    /*
     * Employee profile does not exist.
     */
    if ($result->num_rows === 0) {

        $stmt->close();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Employee profile not found."
        ]);

        exit;
    }


    /*
     * Fetch employee profile.
     */
    $employee = $result->fetch_assoc();

    $stmt->close();


    /*
     * Return profile.
     */
    echo json_encode([
        "success" => true,
        "message" => "Employee profile retrieved successfully.",
        "data" => [
            "employee" => [
                "employee_id" => (int)$employee["employee_id"],
                "employee_number" => $employee["employee_number"],

                "name" => [
                    "first_name" => $employee["first_name"],
                    "middle_name" => $employee["middle_name"],
                    "last_name" => $employee["last_name"]
                ],

                "gender" => $employee["gender"],
                "date_of_birth" => $employee["date_of_birth"],
                "national_id" => $employee["national_id"],

                "contact" => [
                    "email" => $employee["email"],
                    "phone" => $employee["phone"],
                    "address" => $employee["address"]
                ],

                "employment" => [
                    "department_id" => (int)$employee["department_id"],
                    "position_id" => (int)$employee["position_id"],
                    "employment_type" => $employee["employment_type"],
                    "hire_date" => $employee["hire_date"],
                    "employment_status" => $employee["employment_status"]
                ],

                "account" => [
                    "user_id" => $employee["user_id"]
                        !== null
                        ? (int)$employee["user_id"]
                        : null,

                    "username" => $employee["username"],

                    "account_status" => $employee["account_status"],

                    "last_login" => $employee["last_login"],

                    "role_id" => $employee["role_id"]
                        !== null
                        ? (int)$employee["role_id"]
                        : null,

                    "role_name" => $employee["role_name"]
                ],

                "timestamps" => [
                    "created_at" => $employee["created_at"],
                    "updated_at" => $employee["updated_at"]
                ]
            ]
        ]
    ]);

} catch (Exception $e) {
    if ($e instanceof ApiError) throw $e;

    /*
     * Never expose database or internal errors
     * to the API client.
     */
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve employee profile."
    ]);
}

?>
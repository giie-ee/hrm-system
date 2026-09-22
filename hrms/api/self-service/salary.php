<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * Self-Service Salary API
 *
 * Allows the authenticated employee to view
 * their currently effective salary.
 *
 * IMPORTANT:
 * The employee ID is taken from the authenticated
 * PHP session. It is NOT accepted from the client.
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
 * Get the authenticated employee ID.
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


try {

    /*
     * Retrieve the employee's currently effective salary.
     *
     * A salary is considered current when:
     *
     * 1. salary_status = Active
     * 2. effective_from is today or earlier
     * 3. effective_to is NULL OR today is on/before effective_to
     *
     * If more than one record qualifies, the most recent
     * effective salary is returned.
     */
    $sql = "
        SELECT
            salary_id,
            employee_id,
            basic_salary,
            effective_from,
            effective_to,
            salary_status,
            created_at,
            updated_at

        FROM employee_salaries

        WHERE employee_id = ?
          AND salary_status = 'Active'
          AND effective_from <= CURRENT_DATE
          AND (
                effective_to IS NULL
                OR effective_to >= CURRENT_DATE
          )

        ORDER BY
            effective_from DESC,
            salary_id DESC

        LIMIT 1
    ";


    /*
     * Prepare statement.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception("Failed to prepare salary query.");
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

        throw new Exception("Failed to retrieve salary information.");
    }


    /*
     * Get result.
     */
    $result = $stmt->get_result();


    /*
     * No current salary exists.
     */
    if ($result->num_rows === 0) {

        $stmt->close();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "No current salary record found."
        ]);

        exit;
    }


    /*
     * Retrieve salary record.
     */
    $salary = $result->fetch_assoc();

    $stmt->close();


    /*
     * Return salary information.
     */
    echo json_encode([
        "success" => true,
        "message" => "Salary information retrieved successfully.",
        "data" => [
            "salary" => [
                "salary_id" => (int)$salary["salary_id"],
                "employee_id" => (int)$salary["employee_id"],
                "basic_salary" => (float)$salary["basic_salary"],
                "effective_from" => $salary["effective_from"],
                "effective_to" => $salary["effective_to"],
                "salary_status" => $salary["salary_status"],
                "created_at" => $salary["created_at"],
                "updated_at" => $salary["updated_at"]
            ]
        ]
    ]);

} catch (Exception $e) {
    if ($e instanceof ApiError) throw $e;

    /*
     * Do not expose internal database errors.
     */
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve salary information."
    ]);
}

?>

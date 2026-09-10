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
 * Employee ID is required.
 */
$employee_id = $_GET["employee_id"] ?? null;


/*
 * Year is optional.
 * If not supplied, use the current year.
 */
$year = $_GET["year"] ?? date("Y");


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


/*
 * Validate year.
 */
if (
    !preg_match("/^\d{4}$/", (string)$year) ||
    (int)$year < 2000 ||
    (int)$year > 2100
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Year must be a valid four-digit year."
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
     * Retrieve leave balances for the employee.
     *
     * We join leave_types so the frontend receives
     * the leave name together with its balance.
     */
    $sql = "
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
            ON lb.leave_type_id = lt.leave_type_id
        WHERE lb.employee_id = ?
        AND lb.year = ?
        ORDER BY lt.leave_name ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare leave balance query.");
    }

    $year_int = (int)$year;

    $stmt->bind_param(
        "ii",
        $employee_id,
        $year_int
    );

    $stmt->execute();

    $result = $stmt->get_result();


    /*
     * Build response.
     */
    $balances = [];

    while ($row = $result->fetch_assoc()) {

        $balances[] = [
            "leave_balance_id" => (int)$row["leave_balance_id"],
            "employee_id" => (int)$row["employee_id"],
            "leave_type_id" => (int)$row["leave_type_id"],
            "leave_name" => $row["leave_name"],
            "description" => $row["description"],
            "total_days" => (int)$row["total_days"],
            "used_days" => (int)$row["used_days"],
            "remaining_days" => (int)$row["remaining_days"],
            "year" => (int)$row["year"]
        ];
    }


    /*
     * Return successful response.
     */
    echo json_encode([
        "success" => true,
        "message" => "Leave balances retrieved successfully.",
        "employee_id" => (int)$employee_id,
        "year" => $year_int,
        "count" => count($balances),
        "data" => $balances
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve leave balances."
    ]);

}

?>
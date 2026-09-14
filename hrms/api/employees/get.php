<?php

require_once "../../includes/cors.php";

header("Content-Type: application/json");

require_once "../../config/database.php";
require_once "../../includes/auth.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);

    exit;
}

$employee_id = $_GET["employee_id"] ?? null;
$employment_status = $_GET["employment_status"] ?? null;
$employment_type = $_GET["employment_type"] ?? null;
$search = trim($_GET["search"] ?? "");

if ($employee_id !== null) {

    if (
        !filter_var($employee_id, FILTER_VALIDATE_INT) ||
        (int)$employee_id <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Employee ID must be a valid positive integer."
        ]);

        exit;
    }

    $employee_id = (int)$employee_id;
}

if ($employment_status !== null) {
    $employment_status = trim($employment_status);

    if ($employment_status === "") {
        $employment_status = null;
    }
}

if ($employment_type !== null) {
    $employment_type = trim($employment_type);

    if ($employment_type === "") {
        $employment_type = null;
    }
}

try {

    $sql = "
        SELECT
            employee_id,
            employee_number,
            first_name,
            last_name,
            employment_type,
            employment_status
        FROM employees
    ";

    $conditions = [];
    $parameters = [];
    $types = "";

    if ($employee_id !== null) {
        $conditions[] = "employee_id = ?";
        $parameters[] = $employee_id;
        $types .= "i";
    }

    if ($employment_status !== null) {
        $conditions[] = "employment_status = ?";
        $parameters[] = $employment_status;
        $types .= "s";
    }

    if ($employment_type !== null) {
        $conditions[] = "employment_type = ?";
        $parameters[] = $employment_type;
        $types .= "s";
    }

    if ($search !== "") {
        $conditions[] = "(
            employee_number LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
        )";

        $search_value = "%" . $search . "%";
        $parameters[] = $search_value;
        $parameters[] = $search_value;
        $parameters[] = $search_value;
        $types .= "sss";
    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY last_name ASC, first_name ASC, employee_id ASC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare employee query.");
    }

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

    if (!$stmt->execute()) {
        throw new Exception("Failed to retrieve employees.");
    }

    $result = $stmt->get_result();
    $employees = [];

    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            "employee_id" => (int)$row["employee_id"],
            "employee_number" => $row["employee_number"],
            "first_name" => $row["first_name"],
            "last_name" => $row["last_name"],
            "employment_type" => $row["employment_type"],
            "employment_status" => $row["employment_status"]
        ];
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" => "Employees retrieved successfully.",
        "count" => count($employees),
        "data" => $employees
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve employees."
    ]);
}

?>

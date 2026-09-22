<?php
define('HRMS_METHOD', 'POST');
require_once __DIR__ . '/../../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../../includes/auth.php";

requireRole(["Admin", "HR"]);


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
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
|--------------------------------------------------------------------------
| READ REQUEST BODY
|--------------------------------------------------------------------------
*/

$input = input();

if (!is_array($input)) {
    $input = $_POST;
}


/*
|--------------------------------------------------------------------------
| VALIDATE CYCLE NAME
|--------------------------------------------------------------------------
*/

$cycleName = trim($input["cycle_name"] ?? "");

if ($cycleName === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Cycle name is required."
    ]);

    exit;
}

if (strlen($cycleName) > 150) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Cycle name must not exceed 150 characters."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE DESCRIPTION
|--------------------------------------------------------------------------
*/

$description = trim($input["description"] ?? "");

if (strlen($description) > 65535) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Description is too long."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE DATES
|--------------------------------------------------------------------------
*/

$startDate = trim($input["start_date"] ?? "");
$endDate   = trim($input["end_date"] ?? "");

if ($startDate === "" || $endDate === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Start date and end date are required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| DATE FORMAT VALIDATION
|--------------------------------------------------------------------------
*/

$startDateObject = DateTime::createFromFormat("Y-m-d", $startDate);
$endDateObject   = DateTime::createFromFormat("Y-m-d", $endDate);

$startValid =
    $startDateObject &&
    $startDateObject->format("Y-m-d") === $startDate;

$endValid =
    $endDateObject &&
    $endDateObject->format("Y-m-d") === $endDate;

if (!$startValid || !$endValid) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Dates must use the YYYY-MM-DD format."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| DATE ORDER VALIDATION
|--------------------------------------------------------------------------
*/

if ($startDate > $endDate) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Start date cannot be later than end date."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
*/

$status = trim($input["status"] ?? "Draft");

$allowedStatuses = [
    "Draft",
    "Active",
    "Closed",
    "Archived"
];

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid performance cycle status."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$createdBy = (int) ($_SESSION["user_id"] ?? 0);

if ($createdBy <= 0) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Authentication required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK DUPLICATE CYCLE NAME
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();
query('SELECT role_id FROM roles ORDER BY role_id FOR UPDATE');
register_shutdown_function(function() use ($conn) { try { $conn->rollback(); } catch (Throwable $e) {} });
$stmt = $conn->prepare("
    SELECT cycle_id
    FROM performance_cycles
    WHERE cycle_name = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to validate performance cycle."
    ]);

    exit;
}

$stmt->bind_param("s", $cycleName);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to validate performance cycle."
    ]);

    exit;
}

$result = $stmt->get_result();
$existingCycle = $result->fetch_assoc();

$stmt->close();

if ($existingCycle) {
    http_response_code(409);

    echo json_encode([
        "success" => false,
        "message" => "A performance cycle with this name already exists.",
        "cycle_id" => (int) $existingCycle["cycle_id"]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| INSERT PERFORMANCE CYCLE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO performance_cycles
    (
        cycle_name,
        description,
        start_date,
        end_date,
        status,
        created_by
    )
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to create performance cycle."
    ]);

    exit;
}

$stmt->bind_param(
    "sssssi",
    $cycleName,
    $description,
    $startDate,
    $endDate,
    $status,
    $createdBy
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to create performance cycle."
    ]);

    exit;
}

$cycleId = $stmt->insert_id;

$stmt->close();


/*
|--------------------------------------------------------------------------
| SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/

audit('performance.cycle-created','performance_cycles',(int)$cycleId);
$conn->commit();
http_response_code(201);

echo json_encode([
    "success" => true,
    "message" => "Performance cycle created successfully.",
    "data" => [
        "cycle_id" => (int) $cycleId,
        "cycle_name" => $cycleName,
        "description" => $description,
        "start_date" => $startDate,
        "end_date" => $endDate,
        "status" => $status,
        "created_by" => $createdBy
    ]
]);

exit;
?>

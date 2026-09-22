<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../../includes/auth.php";

requireRole(["Admin", "HR", "Manager"]);


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
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
|--------------------------------------------------------------------------
| OPTIONAL FILTERS
|--------------------------------------------------------------------------
*/

$cycleId = isset($_GET["cycle_id"])
    ? (int) $_GET["cycle_id"]
    : 0;

$status = trim($_GET["status"] ?? "");


/*
|--------------------------------------------------------------------------
| VALIDATE CYCLE ID
|--------------------------------------------------------------------------
*/

if ($cycleId < 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid cycle ID."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    "Draft",
    "Active",
    "Closed",
    "Archived"
];

if ($status !== "" && !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid performance cycle status."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        pc.cycle_id,
        pc.cycle_name,
        pc.description,
        pc.start_date,
        pc.end_date,
        pc.status,
        pc.created_by,
        pc.created_at,
        pc.updated_at,

        u.username AS created_by_username,

        COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM manager_assignments ma WHERE ma.employee_id=pg.employee_id AND ma.manager_employee_id=".(int)getCurrentEmployeeId()." AND ma.status='Active') OR ".(isAdminOrHR()?1:0)."=1 THEN pg.goal_id END) AS total_goals,
        COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM manager_assignments ma WHERE ma.employee_id=pr.employee_id AND ma.manager_employee_id=".(int)getCurrentEmployeeId()." AND ma.status='Active') OR ".(isAdminOrHR()?1:0)."=1 THEN pr.review_id END) AS total_reviews

    FROM performance_cycles pc

    INNER JOIN users u
        ON u.user_id = pc.created_by

    LEFT JOIN performance_goals pg
        ON pg.cycle_id = pc.cycle_id

    LEFT JOIN performance_reviews pr
        ON pr.cycle_id = pc.cycle_id

    WHERE 1 = 1
";

$params = [];
$types = "";


/*
|--------------------------------------------------------------------------
| CYCLE ID FILTER
|--------------------------------------------------------------------------
*/

if ($cycleId > 0) {
    $sql .= " AND pc.cycle_id = ? ";
    $params[] = $cycleId;
    $types .= "i";
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status !== "") {
    $sql .= " AND pc.status = ? ";
    $params[] = $status;
    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| GROUPING
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        pc.cycle_id,
        pc.cycle_name,
        pc.description,
        pc.start_date,
        pc.end_date,
        pc.status,
        pc.created_by,
        pc.created_at,
        pc.updated_at,
        u.username
";


/*
|--------------------------------------------------------------------------
| ORDERING
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        pc.start_date DESC,
        pc.cycle_id DESC
";


/*
|--------------------------------------------------------------------------
| PREPARE QUERY
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve performance cycles."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| BIND PARAMETERS
|--------------------------------------------------------------------------
*/

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve performance cycles."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| FETCH RESULTS
|--------------------------------------------------------------------------
*/

$result = $stmt->get_result();

$cycles = [];

while ($row = $result->fetch_assoc()) {

    $cycles[] = [
        "cycle_id" => (int) $row["cycle_id"],

        "cycle_name" => $row["cycle_name"],

        "description" => $row["description"],

        "start_date" => $row["start_date"],

        "end_date" => $row["end_date"],

        "status" => $row["status"],

        "created_by" => (int) $row["created_by"],

        "created_by_username" => $row["created_by_username"],

        "total_goals" => (int) $row["total_goals"],

        "total_reviews" => (int) $row["total_reviews"],

        "created_at" => $row["created_at"],

        "updated_at" => $row["updated_at"]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Performance cycles retrieved successfully.",
    "count" => count($cycles),
    "data" => $cycles
]);

exit;
?>
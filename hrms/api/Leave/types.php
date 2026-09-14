<?php

require_once "../../includes/cors.php";

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


try {

    /*
     * Retrieve active leave types.
     *
     * Inactive leave types are deliberately excluded
     * because employees should not be able to request
     * them through the active system.
     */
    $sql = "
        SELECT
            leave_type_id,
            leave_name,
            description,
            default_days,
            status
        FROM leave_types
        WHERE status = 'Active'
        ORDER BY leave_name ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare leave type query.");
    }

    $stmt->execute();

    $result = $stmt->get_result();


    /*
     * Build response data.
     */
    $leave_types = [];

    while ($row = $result->fetch_assoc()) {

        $leave_types[] = [
            "leave_type_id" => (int)$row["leave_type_id"],
            "leave_name" => $row["leave_name"],
            "description" => $row["description"],
            "default_days" => (int)$row["default_days"],
            "status" => $row["status"]
        ];
    }


    /*
     * Return successful response.
     */
    echo json_encode([
        "success" => true,
        "message" => "Leave types retrieved successfully.",
        "count" => count($leave_types),
        "data" => $leave_types
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve leave types."
    ]);

}

?>
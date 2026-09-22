<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireRole(["Manager"]);

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);
    exit;
}

$managerEmployeeId = getCurrentEmployeeId();

if (!$managerEmployeeId) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Manager employee profile not found."
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | 1. TEAM SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_team_members,
            SUM(CASE WHEN e.employment_status = 'Active' THEN 1 ELSE 0 END) AS active_team_members,
            SUM(CASE WHEN e.employment_status = 'Inactive' THEN 1 ELSE 0 END) AS inactive_team_members,
            SUM(CASE WHEN e.employment_status = 'Terminated' THEN 1 ELSE 0 END) AS terminated_team_members
        FROM manager_assignments ma
        INNER JOIN employees e
            ON e.employee_id = ma.employee_id
        WHERE ma.manager_employee_id = ?
          AND ma.status = 'Active'
    ");

    if (!$stmt) {
        throw new Exception("Team summary prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $managerEmployeeId);

    if (!$stmt->execute()) {
        throw new Exception("Team summary execute failed: " . $stmt->error);
    }

    $teamResult = $stmt->get_result();
    $teamSummary = $teamResult->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | 2. TODAY'S ATTENDANCE SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(a.attendance_id) AS total_records,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status = 'Half-Day' THEN 1 ELSE 0 END) AS half_day,
            SUM(CASE WHEN a.status = 'On Leave' THEN 1 ELSE 0 END) AS on_leave,
            COALESCE(SUM(a.hours_worked), 0) AS total_hours_worked
        FROM manager_assignments ma
        INNER JOIN attendance a
            ON a.employee_id = ma.employee_id
        WHERE ma.manager_employee_id = ?
          AND ma.status = 'Active'
          AND a.attendance_date = CURRENT_DATE
    ");

    if (!$stmt) {
        throw new Exception("Attendance summary prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $managerEmployeeId);

    if (!$stmt->execute()) {
        throw new Exception("Attendance summary execute failed: " . $stmt->error);
    }

    $attendanceResult = $stmt->get_result();
    $attendanceSummary = $attendanceResult->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | 3. LEAVE SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(lr.leave_request_id) AS total_requests,
            SUM(CASE WHEN lr.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN lr.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN lr.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN lr.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM manager_assignments ma
        INNER JOIN leave_requests lr
            ON lr.employee_id = ma.employee_id
        WHERE ma.manager_employee_id = ?
          AND ma.status = 'Active'
    ");

    if (!$stmt) {
        throw new Exception("Leave summary prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $managerEmployeeId);

    if (!$stmt->execute()) {
        throw new Exception("Leave summary execute failed: " . $stmt->error);
    }

    $leaveResult = $stmt->get_result();
    $leaveSummary = $leaveResult->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | 4. ATTENDANCE RATE
    |--------------------------------------------------------------------------
    */

    $totalAttendanceRecords = (int)($attendanceSummary["total_records"] ?? 0);
    $present = (int)($attendanceSummary["present"] ?? 0);

    $attendanceRate = 0;

    if ($totalAttendanceRecords > 0) {
        $attendanceRate = round(
            ($present / $totalAttendanceRecords) * 100,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 5. RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Manager dashboard summary retrieved successfully.",
        "data" => [
            "manager_employee_id" => $managerEmployeeId,

            "team" => [
                "total_team_members" => (int)($teamSummary["total_team_members"] ?? 0),
                "active_team_members" => (int)($teamSummary["active_team_members"] ?? 0),
                "inactive_team_members" => (int)($teamSummary["inactive_team_members"] ?? 0),
                "terminated_team_members" => (int)($teamSummary["terminated_team_members"] ?? 0)
            ],

            "attendance_today" => [
                "total_records" => $totalAttendanceRecords,
                "present" => $present,
                "absent" => (int)($attendanceSummary["absent"] ?? 0),
                "late" => (int)($attendanceSummary["late"] ?? 0),
                "half_day" => (int)($attendanceSummary["half_day"] ?? 0),
                "on_leave" => (int)($attendanceSummary["on_leave"] ?? 0),
                "total_hours_worked" => (float)($attendanceSummary["total_hours_worked"] ?? 0),
                "attendance_rate" => $attendanceRate
            ],

            "leave" => [
                "total_requests" => (int)($leaveSummary["total_requests"] ?? 0),
                "pending" => (int)($leaveSummary["pending"] ?? 0),
                "approved" => (int)($leaveSummary["approved"] ?? 0),
                "rejected" => (int)($leaveSummary["rejected"] ?? 0),
                "cancelled" => (int)($leaveSummary["cancelled"] ?? 0)
            ]
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve manager dashboard summary."
    ]);

    exit;
}
?>

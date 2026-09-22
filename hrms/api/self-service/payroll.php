<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * Self-Service Payroll API
 *
 * Allows the authenticated employee to view
 * their processed payroll history and payslip
 * information.
 *
 * IMPORTANT:
 * The employee ID is obtained exclusively from
 * the authenticated PHP session.
 *
 * Draft payroll records are NEVER exposed.
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


/*
 * Optional year filter.
 *
 * Example:
 * ?year=2026
 */
$year = $_GET["year"] ?? null;


if ($year !== null) {

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
}


try {

    /*
     * ==========================================================
     * STEP 1
     * Retrieve processed payroll records.
     * ==========================================================
     *
     * Only Processed payroll is visible to employees.
     *
     * Draft payroll must remain internal.
     *
     * Paid payroll is also considered visible because
     * it represents a finalized payroll record.
     */
    $sql = "
        SELECT
            p.payroll_id,
            p.employee_id,
            p.pay_period_start,
            p.pay_period_end,
            p.basic_salary,
            p.total_allowances,
            p.total_deductions,
            p.gross_salary,
            p.net_salary,
            p.payroll_status,
            p.payment_date

        FROM payroll p

        WHERE p.employee_id = ?
          AND p.payroll_status = 'Processed'
    ";


    /*
     * Optional year filter.
     *
     * A payroll belongs to a year based on the
     * beginning of its pay period.
     */
    if ($year !== null) {

        $sql .= "
            AND EXTRACT(YEAR FROM p.pay_period_start) = ?
        ";
    }


    /*
     * Newest payroll periods first.
     */
    $sql .= "
        ORDER BY
            p.pay_period_end DESC,
            p.payroll_id DESC
    ";


    /*
     * Prepare statement.
     */
    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception("Failed to prepare payroll query.");
    }


    /*
     * Bind parameters.
     */
    if ($year !== null) {

        $stmt->bind_param(
            "ii",
            $employee_id,
            $year
        );

    } else {

        $stmt->bind_param(
            "i",
            $employee_id
        );
    }


    /*
     * Execute.
     */
    if (!$stmt->execute()) {

        throw new Exception("Failed to retrieve payroll records.");
    }


    $result = $stmt->get_result();

    $payrolls = [];


    /*
     * ==========================================================
     * STEP 2
     * Process each payroll record.
     * ==========================================================
     */
    while ($payroll = $result->fetch_assoc()) {

        $payroll_id = (int)$payroll["payroll_id"];


        /*
         * ------------------------------------------------------
         * Retrieve payroll items.
         * ------------------------------------------------------
         */
        $item_stmt = $conn->prepare("
            SELECT
                payroll_item_id,
                item_type,
                item_name,
                amount,
                description

            FROM payroll_items

            WHERE payroll_id = ?

            ORDER BY
                item_type ASC,
                item_name ASC,
                payroll_item_id ASC
        ");


        if (!$item_stmt) {

            throw new Exception("Failed to prepare payroll items query.");
        }


        $item_stmt->bind_param(
            "i",
            $payroll_id
        );


        if (!$item_stmt->execute()) {

            throw new Exception("Failed to retrieve payroll items.");
        }


        $item_result = $item_stmt->get_result();


        $allowances = [];
        $deductions = [];


        while ($item = $item_result->fetch_assoc()) {

            $item_data = [
                "payroll_item_id" => (int)$item["payroll_item_id"],
                "item_name" => $item["item_name"],
                "amount" => (float)$item["amount"],
                "description" => $item["description"]
            ];


            if ($item["item_type"] === "Allowance") {

                $allowances[] = $item_data;

            } elseif ($item["item_type"] === "Deduction") {

                $deductions[] = $item_data;
            }
        }


        $item_stmt->close();


        /*
         * ------------------------------------------------------
         * Retrieve payroll attendance summary.
         * ------------------------------------------------------
         */
        $attendance_stmt = $conn->prepare("
            SELECT
                working_days,
                days_present,
                days_absent,
                days_on_leave,
                days_late,
                notes

            FROM payroll_attendance

            WHERE payroll_id = ?
              AND employee_id = ?

            LIMIT 1
        ");


        if (!$attendance_stmt) {

            throw new Exception(
                "Failed to prepare payroll attendance query."
            );
        }


        $attendance_stmt->bind_param(
            "ii",
            $payroll_id,
            $employee_id
        );


        if (!$attendance_stmt->execute()) {

            throw new Exception(
                "Failed to retrieve payroll attendance."
            );
        }


        $attendance_result = $attendance_stmt->get_result();

        $attendance = $attendance_result->fetch_assoc();

        $attendance_stmt->close();


        /*
         * ------------------------------------------------------
         * Build payroll response.
         * ------------------------------------------------------
         */
        $payrolls[] = [
            "payroll_id" => $payroll_id,

            "pay_period" => [
                "start" => $payroll["pay_period_start"],
                "end" => $payroll["pay_period_end"]
            ],

            "earnings" => [
                "basic_salary" => (float)$payroll["basic_salary"],
                "total_allowances" =>
                    (float)$payroll["total_allowances"],
                "gross_salary" =>
                    (float)$payroll["gross_salary"],

                "allowances" => $allowances
            ],

            "deductions" => [
                "total_deductions" =>
                    (float)$payroll["total_deductions"],

                "items" => $deductions
            ],

            "net_salary" => (float)$payroll["net_salary"],

            "status" => $payroll["payroll_status"],

            "payment_date" => $payroll["payment_date"],

            "attendance" => $attendance
                ? [
                    "working_days" =>
                        (int)$attendance["working_days"],

                    "days_present" =>
                        (int)$attendance["days_present"],

                    "days_absent" =>
                        (int)$attendance["days_absent"],

                    "days_on_leave" =>
                        (int)$attendance["days_on_leave"],

                    "days_late" =>
                        (int)$attendance["days_late"],

                    "notes" => $attendance["notes"]
                ]
                : null
        ];
    }


    $stmt->close();


    /*
     * ==========================================================
     * STEP 3
     * Calculate summary.
     * ==========================================================
     */
    $summary = [
        "total_payroll_records" => count($payrolls),
        "total_gross_salary" => 0,
        "total_deductions" => 0,
        "total_net_salary" => 0
    ];


    foreach ($payrolls as $payroll) {

        $summary["total_gross_salary"] +=
            $payroll["earnings"]["gross_salary"];

        $summary["total_deductions"] +=
            $payroll["deductions"]["total_deductions"];

        $summary["total_net_salary"] +=
            $payroll["net_salary"];
    }


    /*
     * ==========================================================
     * STEP 4
     * Return response.
     * ==========================================================
     */
    echo json_encode([
        "success" => true,
        "message" => "Payroll information retrieved successfully.",

        "data" => [
            "employee_id" => $employee_id,

            "year" => $year,

            "summary" => $summary,

            "payrolls" => $payrolls
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
        "message" => "Unable to retrieve payroll information."
    ]);
}

?>

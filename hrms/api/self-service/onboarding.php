<?php
define('HRMS_METHOD', 'GET');
require_once __DIR__ . '/../../includes/bootstrap.php';


header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

requireLogin();


/*
 * ==========================================================
 * SELF-SERVICE ONBOARDING API
 * ==========================================================
 *
 * Allows the authenticated employee to view:
 *
 * 1. Their onboarding information
 * 2. Their onboarding documents
 * 3. Document verification status
 * 4. Basic onboarding progress summary
 *
 * IMPORTANT:
 *
 * The employee ID is obtained exclusively from the
 * authenticated PHP session.
 *
 * The client cannot provide an employee_id to access
 * another employee's onboarding information.
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
 * Optional document status filter.
 *
 * Example:
 *
 * ?document_status=Pending
 *
 * Allowed values are based directly on the
 * onboarding_documents ENUM.
 */
$document_status = $_GET["document_status"] ?? null;


$allowed_document_statuses = [
    "Pending",
    "Submitted",
    "Verified",
    "Rejected"
];


/*
 * Validate optional document status.
 */
if ($document_status !== null) {

    $document_status = trim($document_status);

    if (
        !in_array(
            $document_status,
            $allowed_document_statuses,
            true
        )
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid document status."
        ]);

        exit;
    }
}


try {

    /*
     * ==========================================================
     * STEP 1
     * Retrieve the employee's onboarding record.
     * ==========================================================
     *
     * The employee ID comes from the authenticated session.
     */
    $onboarding_sql = "
        SELECT
            onboarding_id,
            employee_id,
            start_date,
            onboarding_status,
            assigned_hr_id,
            notes,
            completed_at,
            created_at,
            updated_at

        FROM onboarding

        WHERE employee_id = ?

        ORDER BY
            created_at DESC,
            onboarding_id DESC

        LIMIT 1
    ";


    $onboarding_stmt = $conn->prepare($onboarding_sql);

    if (!$onboarding_stmt) {

        throw new Exception(
            "Failed to prepare onboarding query."
        );
    }


    $onboarding_stmt->bind_param(
        "i",
        $employee_id
    );


    if (!$onboarding_stmt->execute()) {

        throw new Exception(
            "Failed to retrieve onboarding information."
        );
    }


    $onboarding_result = $onboarding_stmt->get_result();


    /*
     * Employee does not currently have an onboarding record.
     */
    if ($onboarding_result->num_rows === 0) {

        $onboarding_stmt->close();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "No onboarding record found for this employee."
        ]);

        exit;
    }


    $onboarding = $onboarding_result->fetch_assoc();

    $onboarding_stmt->close();


    $onboarding_id = (int)$onboarding["onboarding_id"];


    /*
     * ==========================================================
     * STEP 2
     * Retrieve onboarding documents.
     * ==========================================================
     */
    $document_sql = "
        SELECT
            document_id,
            onboarding_id,
            document_name,
            document_type,
            document_status,
            document_path,
            verified_by,
            verified_at,
            created_at

        FROM onboarding_documents

        WHERE onboarding_id = ?
    ";


    /*
     * Apply optional document status filter.
     */
    if ($document_status !== null) {

        $document_sql .= "
            AND document_status = ?
        ";
    }


    $document_sql .= "
        ORDER BY
            created_at DESC,
            document_id DESC
    ";


    $document_stmt = $conn->prepare($document_sql);

    if (!$document_stmt) {

        throw new Exception(
            "Failed to prepare onboarding documents query."
        );
    }


    /*
     * Bind parameters.
     */
    if ($document_status !== null) {

        $document_stmt->bind_param(
            "is",
            $onboarding_id,
            $document_status
        );

    } else {

        $document_stmt->bind_param(
            "i",
            $onboarding_id
        );
    }


    if (!$document_stmt->execute()) {

        throw new Exception(
            "Failed to retrieve onboarding documents."
        );
    }


    $document_result = $document_stmt->get_result();


    $documents = [];


    /*
     * Counters for document summary.
     */
    $document_summary = [
        "total" => 0,
        "pending" => 0,
        "submitted" => 0,
        "verified" => 0,
        "rejected" => 0
    ];


    while ($document = $document_result->fetch_assoc()) {

        $status = $document["document_status"];


        /*
         * Update summary counters.
         */
        $document_summary["total"]++;


        switch ($status) {

            case "Pending":
                $document_summary["pending"]++;
                break;

            case "Submitted":
                $document_summary["submitted"]++;
                break;

            case "Verified":
                $document_summary["verified"]++;
                break;

            case "Rejected":
                $document_summary["rejected"]++;
                break;
        }


        /*
         * Add document to response.
         */
        $documents[] = [
            "document_id" => (int)$document["document_id"],

            "document_name" =>
                $document["document_name"],

            "document_type" =>
                $document["document_type"],

            "document_status" =>
                $document["document_status"],

            /*
             * We return the stored path as metadata.
             *
             * The frontend should not automatically expose
             * internal server paths as public URLs.
             */
            "document_path" =>
                null, // Use the authorized documents/download.php endpoint.

            "verified_by" =>
                $document["verified_by"] !== null
                    ? (int)$document["verified_by"]
                    : null,

            "verified_at" =>
                $document["verified_at"],

            "created_at" =>
                $document["created_at"]
        ];
    }


    $document_stmt->close();


    /*
     * ==========================================================
     * STEP 3
     * Calculate basic document progress.
     * ==========================================================
     *
     * We calculate this only from documents that actually
     * exist in the database.
     *
     * We do NOT claim that all required onboarding documents
     * have been completed because the current schema does not
     * contain a "required" field.
     */
    $verified_count =
        $document_summary["verified"];

    $total_documents =
        $document_summary["total"];


    $verification_percentage = 0;


    if ($total_documents > 0) {

        $verification_percentage =
            round(
                ($verified_count / $total_documents) * 100,
                2
            );
    }


    /*
     * ==========================================================
     * STEP 4
     * Return onboarding information.
     * ==========================================================
     */
    echo json_encode([
        "success" => true,

        "message" =>
            "Onboarding information retrieved successfully.",

        "data" => [

            "employee_id" =>
                $employee_id,

            "onboarding" => [

                "onboarding_id" =>
                    $onboarding_id,

                "start_date" =>
                    $onboarding["start_date"],

                "status" =>
                    $onboarding["onboarding_status"],

                "assigned_hr_id" =>
                    $onboarding["assigned_hr_id"] !== null
                        ? (int)$onboarding["assigned_hr_id"]
                        : null,

                "notes" =>
                    $onboarding["notes"],

                "completed_at" =>
                    $onboarding["completed_at"],

                "created_at" =>
                    $onboarding["created_at"],

                "updated_at" =>
                    $onboarding["updated_at"]
            ],

            "document_summary" => [

                "total" =>
                    $document_summary["total"],

                "pending" =>
                    $document_summary["pending"],

                "submitted" =>
                    $document_summary["submitted"],

                "verified" =>
                    $document_summary["verified"],

                "rejected" =>
                    $document_summary["rejected"],

                "verification_percentage" =>
                    $verification_percentage
            ],

            "documents" =>
                $documents
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
            "Unable to retrieve onboarding information."
    ]);
}

?>
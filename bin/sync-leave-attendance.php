<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

$projectRoot = dirname(__DIR__);
$backendRoot = (string) (getenv('HRMS_BACKEND_ROOT') ?: $projectRoot . '/hrms');
require_once $backendRoot . '/config/database.php';
require_once $backendRoot . '/includes/core.php';
require_once $backendRoot . '/lib/LeaveAttendanceSync.php';

echo 'Marked ' . attendanceSyncApprovedLeave(true) . " attendance day(s) as On Leave.\n";

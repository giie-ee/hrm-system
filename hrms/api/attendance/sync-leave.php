<?php
define('HRMS_METHOD', 'POST');
define('HRMS_PUBLIC', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
$action = 'sync-leave';
require __DIR__ . '/../../includes/modules/attendance.php';

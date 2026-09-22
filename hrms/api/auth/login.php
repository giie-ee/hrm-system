<?php

declare(strict_types=1);

define('HRMS_METHOD', 'POST');
define('HRMS_PUBLIC', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
$action = 'login';
require __DIR__ . '/../../includes/modules/authentication.php';

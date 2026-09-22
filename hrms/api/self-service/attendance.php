<?php
define('HRMS_METHOD','GET');
require_once __DIR__.'/../../includes/bootstrap.php';
$params=[ownEmployee()]; $where='employee_id=?';
if (isset($_GET['date'])) { $where.=' AND attendance_date=?'; $params[]=dateValue($_GET['date']); }
if (isset($_GET['status'])) { $where.=' AND status=?'; $params[]=choice($_GET['status'],['Present','Absent','Late','Half-Day','On Leave']); }
reply(rows('SELECT attendance_id,employee_id,attendance_date,check_in,check_out,hours_worked,status,notes FROM attendance WHERE '.$where.' ORDER BY attendance_date DESC'.pageLimit(),$params));

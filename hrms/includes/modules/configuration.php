<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
if ($action==='get') {
    requireLogin(); reply(['departments'=>rows('SELECT * FROM departments ORDER BY department_name'),'positions'=>rows('SELECT * FROM positions ORDER BY position_name'),'roles'=>isAdminOrHR()?rows('SELECT role_id,role_name FROM roles'):[]]);
}
requireRole(['Admin','HR']);
if ($action==='department') {
    $name=textValue($b['department_name']??null,'department_name',100); $desc=textValue($b['description']??'','description',255,false);
    $id=transaction(function() use($name,$desc) { query('INSERT INTO departments(department_name,description) VALUES (?,?)',[$name,$desc]); $id=inserted(); audit('department.created','departments',$id); return $id; }); reply(['department_id'=>$id],'Department created.',201);
}
if ($action==='position') {
    $name=textValue($b['position_name']??null,'position_name',100); $department=id($b['department_id']??null); record('departments','department_id',$department); $desc=textValue($b['description']??'','description',255,false);
    $id=transaction(function() use($name,$department,$desc) { query('INSERT INTO positions(position_name,department_id,description) VALUES (?,?,?)',[$name,$department,$desc]); $id=inserted(); audit('position.created','positions',$id); return $id; }); reply(['position_id'=>$id],'Position created.',201);
}
if ($action==='leave-type') {
    $name=textValue($b['leave_name']??null,'leave_name',100); $days=number($b['default_days']??null,0,366,'default_days'); if (floor($days)!==$days) fail(400,'Leave days must be whole days.'); $desc=textValue($b['description']??'','description',255,false);
    $id=transaction(function() use($name,$days,$desc) { query('INSERT INTO leave_types(leave_name,description,default_days) VALUES (?,?,?)',[$name,$desc,(int)$days]); $id=inserted(); audit('leave-type.created','leave_types',$id); return $id; }); reply(['leave_type_id'=>$id],'Leave type created.',201);
}
if ($action==='leave-allocation') {
    $employee=id($b['employee_id']??null); $type=id($b['leave_type_id']??null); $year=id($b['year']??null,'year'); if ($year<2000 || $year>2100) fail(400,'Invalid year.'); $days=number($b['total_days']??null,0,366,'total_days'); if (floor($days)!==$days) fail(400,'Leave days must be whole days.');
    transaction(function() use($employee,$type,$year,$days) { record('employees','employee_id',$employee,true); record('leave_types','leave_type_id',$type); $old=one('SELECT * FROM leave_balances WHERE employee_id=? AND leave_type_id=? AND year=? FOR UPDATE',[$employee,$type,$year]); if ($old && $days<(int)$old['used_days']) fail(409,'Allocation cannot be below days already used.');
        query('INSERT INTO leave_balances(employee_id,leave_type_id,year,total_days,remaining_days) VALUES (?,?,?,?,?) ON CONFLICT (employee_id,leave_type_id,year) DO UPDATE SET total_days=EXCLUDED.total_days,remaining_days=EXCLUDED.total_days-leave_balances.used_days',[$employee,$type,$year,(int)$days,(int)$days]); audit('leave.allocated','employees',$employee,['leave_type_id'=>$type,'year'=>$year,'total_days'=>$days]);
    }); reply(['employee_id'=>$employee,'leave_type_id'=>$type,'year'=>$year]);
}

<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
if ($action==='get') {
    requireLogin(); reply(['departments'=>rows('SELECT * FROM departments ORDER BY department_name'),'positions'=>rows('SELECT * FROM positions ORDER BY position_name'),'roles'=>isAdminOrHR()?rows('SELECT role_id,role_name FROM roles'):[]]);
}
if ($action==='work-policy') {
    requireLogin();
    $policy=one("SELECT * FROM work_policies WHERE status='Active' ORDER BY work_policy_id LIMIT 1")
        ?? fail(404,'No active working-hour policy is configured.');
    $policy['work_policy_id']=(int)$policy['work_policy_id'];
    $policy['grace_minutes']=(int)$policy['grace_minutes'];
    $policy['early_departure_grace_minutes']=(int)$policy['early_departure_grace_minutes'];
    $policy['minimum_half_day_hours']=(float)$policy['minimum_half_day_hours'];
    $policy['days']=array_map(static function(array $day): array {
        $day['work_policy_day_id']=(int)$day['work_policy_day_id'];
        $day['work_policy_id']=(int)$day['work_policy_id'];
        $day['day_of_week']=(int)$day['day_of_week'];
        $day['is_working_day']=filter_var($day['is_working_day'],FILTER_VALIDATE_BOOLEAN);
        $day['expected_hours']=(float)$day['expected_hours'];
        return $day;
    },rows('SELECT * FROM work_policy_days WHERE work_policy_id=? ORDER BY day_of_week',[$policy['work_policy_id']]));
    reply($policy,'Working-hour policy retrieved.');
}
if ($action==='work-policy-settings') {
    requireRole(['Admin']);
    $policyId=id($b['work_policy_id']??null,'work_policy_id');
    $name=textValue($b['policy_name']??null,'policy_name',120);
    $timezone=textValue($b['timezone']??null,'timezone',64);
    try { new DateTimeZone($timezone); } catch (Throwable) { fail(400,'Invalid timezone.'); }
    $grace=number($b['grace_minutes']??null,0,180,'grace_minutes');
    $early=number($b['early_departure_grace_minutes']??null,0,180,'early_departure_grace_minutes');
    $half=number($b['minimum_half_day_hours']??null,0,24,'minimum_half_day_hours');
    if (floor($grace)!==$grace || floor($early)!==$early) fail(400,'Grace periods must use whole minutes.');
    transaction(function() use($policyId,$name,$timezone,$grace,$early,$half) {
        record('work_policies','work_policy_id',$policyId,true);
        query('UPDATE work_policies SET policy_name=?,timezone=?,grace_minutes=?,early_departure_grace_minutes=?,minimum_half_day_hours=? WHERE work_policy_id=?',[$name,$timezone,(int)$grace,(int)$early,$half,$policyId]);
        audit('work-policy.settings','work_policies',$policyId,['timezone'=>$timezone,'grace_minutes'=>(int)$grace,'early_departure_grace_minutes'=>(int)$early,'minimum_half_day_hours'=>$half]);
    });
    reply(['work_policy_id'=>$policyId],'Working-hour settings updated.');
}
if ($action==='work-policy-day') {
    requireRole(['Admin']);
    $policyId=id($b['work_policy_id']??null,'work_policy_id');
    $day=number($b['day_of_week']??null,0,6,'day_of_week');
    if (floor($day)!==$day) fail(400,'day_of_week must be a whole number.');
    $working=filter_var($b['is_working_day']??null,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
    if ($working===null) fail(400,'is_working_day must be true or false.');
    $start=null; $end=null; $hours=0.0;
    if ($working) {
        $start=textValue($b['start_time']??null,'start_time',8);
        $end=textValue($b['end_time']??null,'end_time',8);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/D',$start)
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/D',$end)) fail(400,'Times must use HH:MM or HH:MM:SS.');
        if ($end<=$start) fail(400,'End time must be later than start time.');
        $hours=number($b['expected_hours']??null,0.25,24,'expected_hours');
    }
    transaction(function() use($policyId,$day,$working,$start,$end,$hours) {
        record('work_policies','work_policy_id',$policyId,true);
        query('INSERT INTO work_policy_days(work_policy_id,day_of_week,is_working_day,start_time,end_time,expected_hours) VALUES (?,?,?,?,?,?) ON CONFLICT (work_policy_id,day_of_week) DO UPDATE SET is_working_day=EXCLUDED.is_working_day,start_time=EXCLUDED.start_time,end_time=EXCLUDED.end_time,expected_hours=EXCLUDED.expected_hours',[$policyId,(int)$day,$working,$start,$end,$hours]);
        audit('work-policy.day','work_policies',$policyId,['day_of_week'=>(int)$day,'is_working_day'=>$working,'start_time'=>$start,'end_time'=>$end,'expected_hours'=>$hours]);
    });
    reply(['work_policy_id'=>$policyId,'day_of_week'=>(int)$day],'Working day updated.');
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

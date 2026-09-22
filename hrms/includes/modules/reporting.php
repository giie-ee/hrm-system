<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
if ($section==='notifications') {
    if ($action==='get') reply(rows('SELECT notification_id,title,entity,entity_id,read_at,created_at FROM notifications WHERE user_id=? ORDER BY notification_id DESC'.pageLimit(),[(int)$_SESSION['user_id']]));
    $id=id($b['notification_id']??null); $n=one('SELECT notification_id FROM notifications WHERE notification_id=? AND user_id=?',[$id,(int)$_SESSION['user_id']]); if (!$n) fail(404,'Notification not found.');
    query('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE notification_id=? AND user_id=?',[$id,(int)$_SESSION['user_id']]); reply(['notification_id'=>$id]);
}
if ($section==='announcements') {
    if ($action==='get') reply(rows("SELECT announcement_id,title,body,audience,expires_on,created_at FROM announcements WHERE (audience='All' OR audience=?) AND (expires_on IS NULL OR expires_on>=CURRENT_DATE) ORDER BY announcement_id DESC".pageLimit(),[$_SESSION['role_name']]));
    requireRole(['Admin','HR']); $title=textValue($b['title']??null,'title',150); $body=textValue($b['body']??null,'body',10000); $audience=choice($b['audience']??'All',['All','Admin','HR','Manager','Employee'],'audience'); $expires=isset($b['expires_on'])?dateValue($b['expires_on']):null;
    if ($expires && $expires<date('Y-m-d')) fail(400,'Announcement expiry is in the past.');
    $id=transaction(function() use($title,$body,$audience,$expires) { query('INSERT INTO announcements(title,body,audience,expires_on,created_by) VALUES (?,?,?,?,?)',[$title,$body,$audience,$expires,(int)$_SESSION['user_id']]); $id=inserted(); audit('announcement.created','announcements',$id); return $id; }); reply(['announcement_id'=>$id],'Announcement created.',201);
}
if ($section==='audit') { requireRole(['Admin']); reply(rows('SELECT audit_id,user_id,action,entity,entity_id,metadata,ip_address,created_at FROM audit_logs ORDER BY audit_id DESC'.pageLimit())); }
requireRole(['Admin','HR']);
$start=dateValue($b['start_date']??date('Y-01-01')); $end=dateValue($b['end_date']??date('Y-12-31')); if ($start>$end) fail(400,'Invalid report range.');
$employees=one("SELECT COUNT(*) total,SUM(CASE WHEN employment_status='Active' THEN 1 ELSE 0 END) active FROM employees");
$attendance=one("SELECT COUNT(*) records,COALESCE(SUM(CASE WHEN status IN ('Present','Late','Half-Day') THEN 1 ELSE 0 END),0) attended,COALESCE(SUM(hours_worked),0) hours FROM attendance WHERE attendance_date BETWEEN ? AND ?",[$start,$end]);
$attendance['rate_recorded_days_percent']=$attendance['records']?round(100*(int)$attendance['attended']/(int)$attendance['records'],2):0;
$attendance['definition']='Present, Late or Half-Day records / all recorded attendance days. Not a scheduled-workday measure.';
reply([
 'period'=>['start_date'=>$start,'end_date'=>$end], 'employees'=>$employees,
 'departments'=>rows('SELECT d.department_id,d.department_name,COUNT(e.employee_id) employees FROM departments d LEFT JOIN employees e ON e.department_id=d.department_id GROUP BY d.department_id,d.department_name'),
 'positions'=>rows('SELECT p.position_id,p.position_name,COUNT(e.employee_id) employees FROM positions p LEFT JOIN employees e ON e.position_id=p.position_id GROUP BY p.position_id,p.position_name'),
 'attendance'=>$attendance,
 'leave'=>rows('SELECT status,COUNT(*) requests,SUM(number_of_days) days FROM leave_requests WHERE start_date<=? AND end_date>=? GROUP BY status',[$end,$start]),
 'payroll'=>rows("SELECT TO_CHAR(pay_period_start,'YYYY-MM') period,COUNT(*) records,SUM(gross_salary) gross,SUM(total_deductions) deductions,SUM(net_salary) net FROM payroll WHERE payroll_status IN ('Processed','Paid') AND pay_period_start BETWEEN ? AND ? GROUP BY TO_CHAR(pay_period_start,'YYYY-MM')",[$start,$end]),
 'benefits'=>rows('SELECT status,COUNT(*) assignments,SUM(amount) amount FROM employee_benefits GROUP BY status'),
 'onboarding'=>rows('SELECT onboarding_status status,COUNT(*) records FROM onboarding GROUP BY onboarding_status'),
 'recruitment'=>rows('SELECT status,COUNT(*) applications FROM job_applications GROUP BY status'),
 'training'=>rows('SELECT status,COUNT(*) enrollments FROM training_enrollments GROUP BY status'),
 'performance'=>rows('SELECT review_status status,COUNT(*) reviews,AVG(overall_rating) average_final_rating FROM performance_reviews GROUP BY review_status'),
 'scope_note'=>'Employee, benefits, onboarding, recruitment, training and performance totals are all-time; attendance, leave and payroll use the stated date range.'
]);

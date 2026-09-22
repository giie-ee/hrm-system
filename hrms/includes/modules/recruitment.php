<?php
requireRole(['Admin','HR','Manager']); $b=HRMS_METHOD==='POST'?input():$_GET;
function vacancyAccess(array $v): void { if (!isAdminOrHR() && (int)$v['manager_employee_id']!==ownEmployee()) fail(403,'Vacancy is not assigned to you.'); }
if ($action==='vacancies') {
    $params=[]; $where='1=1'; if (!isAdminOrHR()) { $where='manager_employee_id=?'; $params[]=ownEmployee(); }
    if (isset($b['status'])) { $where.=' AND status=?'; $params[]=choice($b['status'],['Draft','Open','Closed','Filled','Cancelled']); }
    reply(rows("SELECT * FROM job_vacancies WHERE $where ORDER BY vacancy_id DESC".pageLimit(),$params));
}
if ($action==='applications') {
    $params=[]; $where='1=1'; if (!isAdminOrHR()) { $where='v.manager_employee_id=?'; $params[]=ownEmployee(); }
    if (isset($b['vacancy_id'])) { $where.=' AND v.vacancy_id=?'; $params[]=id($b['vacancy_id']); }
    if (isset($b['application_id'])) { $where.=' AND a.application_id=?'; $params[]=id($b['application_id']); }
    reply(rows("SELECT a.*,p.full_name,p.email,p.phone,v.title FROM job_applications a JOIN applicants p ON p.applicant_id=a.applicant_id JOIN job_vacancies v ON v.vacancy_id=a.vacancy_id WHERE $where ORDER BY a.application_id DESC".pageLimit(),$params));
}
if ($action==='interviews' || $action==='history') {
    $id=id($b['application_id']??null); $a=record('job_applications','application_id',$id); vacancyAccess(record('job_vacancies','vacancy_id',(int)$a['vacancy_id']));
    if ($action==='interviews') reply(rows('SELECT * FROM interviews WHERE application_id=? ORDER BY scheduled_at'.pageLimit(),[$id]));
    reply(rows("SELECT action,metadata,user_id,created_at FROM audit_logs WHERE entity='job_applications' AND entity_id=? ORDER BY audit_id".pageLimit(),[$id]));
}
if ($action==='create-vacancy' || $action==='update-vacancy') {
    requireRole(['Admin','HR']); $id=$action==='update-vacancy'?id($b['vacancy_id']??null):null;
    $id=transaction(function() use($id,$b) {
        $old=$id?record('job_vacancies','vacancy_id',$id,true):[];
        if ($old && in_array($old['status'],['Filled','Cancelled'],true)) fail(409,'Vacancy is closed permanently.');
        $title=textValue($b['title']??$old['title']??null,'title',150); $desc=textValue($b['description']??$old['description']??null,'description',5000); $requirements=textValue($b['requirements']??$old['requirements']??null,'requirements',5000);
        $department=id($b['department_id']??$old['department_id']??null); $position=id($b['position_id']??$old['position_id']??null);
        $p=record('positions','position_id',$position); if ((int)$p['department_id']!==$department) fail(400,'Position does not belong to department.');
        $manager=isset($b['manager_employee_id'])?id($b['manager_employee_id']):($old['manager_employee_id']??null);
        if ($manager && !one("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.employee_id=? AND r.role_name='Manager' AND u.account_status='Active'",[$manager])) fail(400,'Select an active manager.');
        $closing=dateValue($b['closing_date']??$old['closing_date']??null); $status=choice($b['status']??$old['status']??'Draft',['Draft','Open','Closed','Filled','Cancelled']);
        if (!$id && !in_array($status,['Draft','Open'],true)) fail(400,'New vacancies must be Draft or Open.');
        if ($id && !in_array($status,['Draft'=>['Draft','Open','Cancelled'],'Open'=>['Open','Closed','Cancelled'],'Closed'=>['Closed','Open','Filled','Cancelled']][$old['status']],true)) fail(409,'Invalid vacancy transition.');
        if ($status==='Open' && $closing<date('Y-m-d')) fail(400,'Open vacancy requires a current closing date.');
        if ($status==='Filled' && !one("SELECT application_id FROM job_applications WHERE vacancy_id=? AND status='Hired'",[$id])) fail(409,'A hired applicant is required.');
        if ($id) query('UPDATE job_vacancies SET title=?,description=?,department_id=?,position_id=?,requirements=?,status=?,closing_date=?,manager_employee_id=? WHERE vacancy_id=?',[$title,$desc,$department,$position,$requirements,$status,$closing,$manager,$id]);
        else { query('INSERT INTO job_vacancies(title,description,department_id,position_id,requirements,status,closing_date,manager_employee_id,created_by) VALUES (?,?,?,?,?,?,?,?,?)',[$title,$desc,$department,$position,$requirements,$status,$closing,$manager,(int)$_SESSION['user_id']]); $id=inserted(); }
        audit('recruitment.vacancy-saved','job_vacancies',$id,['status'=>$status]); return $id;
    }); reply(record('job_vacancies','vacancy_id',$id),'Vacancy saved.',$action==='create-vacancy'?201:200);
}
if ($action==='apply') {
    // Applicant intake is HR-assisted; no public account or administrative registration.
    requireRole(['Admin','HR']); $vacancy=id($b['vacancy_id']??null); $name=textValue($b['full_name']??null,'full_name',150); $email=textValue($b['email']??null,'email',100); if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail(400,'Invalid email.');
    $phone=textValue($b['phone']??'','phone',30,false); if ($phone!=='' && !preg_match('/^\+?[0-9 ()-]{7,30}$/D',$phone)) fail(400,'Invalid phone.'); $letter=textValue($b['cover_letter']??'','cover_letter',5000,false);
    $id=transaction(function() use($vacancy,$name,$email,$phone,$letter) {
        $v=record('job_vacancies','vacancy_id',$vacancy,true); if ($v['status']!=='Open' || $v['closing_date']<date('Y-m-d')) fail(409,'Vacancy is not accepting applications.');
        query('INSERT INTO applicants(full_name,email,phone) VALUES (?,?,?) ON CONFLICT (email) DO UPDATE SET full_name=EXCLUDED.full_name,phone=EXCLUDED.phone RETURNING applicant_id',[$name,$email,$phone]); $applicant=inserted();
        query('INSERT INTO job_applications(vacancy_id,applicant_id,cover_letter,created_by) VALUES (?,?,?,?)',[$vacancy,$applicant,$letter,(int)$_SESSION['user_id']]); $id=inserted(); audit('recruitment.submitted','job_applications',$id); if ($v['manager_employee_id']) notifyEmployee((int)$v['manager_employee_id'],'Recruitment application received','job_applications',$id); return $id;
    }); reply(['application_id'=>$id],'Application submitted.',201);
}
if ($action==='decision') {
    requireRole(['Admin','HR']); $id=id($b['application_id']??null); $status=choice($b['status']??null,['Shortlisted','Interview','Offered','Hired','Rejected','Withdrawn']); $notes=textValue($b['decision_notes']??null,'decision_notes',5000);
    transaction(function() use($id,$status,$notes,$b) {
        $initial=record('job_applications','application_id',$id); $v=record('job_vacancies','vacancy_id',(int)$initial['vacancy_id'],true); $a=record('job_applications','application_id',$id,true);
        $next=['Submitted'=>['Shortlisted','Rejected','Withdrawn'],'Shortlisted'=>['Interview','Rejected','Withdrawn'],'Interview'=>['Offered','Rejected','Withdrawn'],'Offered'=>['Hired','Rejected','Withdrawn']];
        if (!in_array($status,$next[$a['status']]??[],true)) fail(409,'Invalid application transition.');
        if (in_array($v['status'],['Filled','Cancelled'],true) && !in_array($status,['Rejected','Withdrawn'],true)) fail(409,'Vacancy is no longer available.');
        if ($status==='Offered' && !one("SELECT interview_id FROM interviews WHERE application_id=? AND status='Completed' AND result='Pass'",[$id])) fail(409,'A passed interview is required before an offer.');
        $employee=null;
        if ($status==='Hired') { $employee=id($b['hired_employee_id']??null); $e=record('employees','employee_id',$employee); if ((int)$e['department_id']!==(int)$v['department_id'] || (int)$e['position_id']!==(int)$v['position_id'] || $e['employment_status']!=='Active') fail(400,'Hired employee must match the vacancy department/position and be Active.'); if (one('SELECT application_id FROM job_applications WHERE hired_employee_id=?',[$employee])) fail(409,'Employee already linked to a hiring decision.'); }
        query('UPDATE job_applications SET status=?,decision_notes=?,hired_employee_id=? WHERE application_id=?',[$status,$notes,$employee,$id]); audit('recruitment.'.$status,'job_applications',$id,['from'=>$a['status'],'to'=>$status]); notifyHR('Recruitment decision recorded','job_applications',$id);
    }); reply(record('job_applications','application_id',$id));
}
if ($action==='schedule-interview') {
    requireRole(['Admin','HR']); $id=id($b['application_id']??null); $interviewer=id($b['interviewer_employee_id']??null); $at=textValue($b['scheduled_at']??null,'scheduled_at',19);
    $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at); if (!$date || $date->format('Y-m-d H:i:s')!==$at || $date->getTimestamp()<time()) fail(400,'Interview requires a future YYYY-MM-DD HH:MM:SS date.');
    $interview=transaction(function() use($id,$interviewer,$at) { $a=record('job_applications','application_id',$id,true); $v=record('job_vacancies','vacancy_id',(int)$a['vacancy_id']); if ($a['status']!=='Interview' || in_array($v['status'],['Filled','Cancelled'],true)) fail(409,'Application must be at Interview stage for an available vacancy.');
        if (!one("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.employee_id=? AND u.account_status='Active' AND (r.role_name IN ('Admin','HR') OR (r.role_name='Manager' AND u.employee_id=?))",[$interviewer,(int)$v['manager_employee_id']])) fail(400,'Interviewer must be HR/Admin or the assigned manager.');
        query('INSERT INTO interviews(application_id,interviewer_employee_id,scheduled_at) VALUES (?,?,?)',[$id,$interviewer,$at]); $i=inserted(); audit('recruitment.interview-scheduled','job_applications',$id,['interview_id'=>$i]); notifyEmployee($interviewer,'Interview scheduled','interviews',$i); return $i;
    }); reply(['interview_id'=>$interview],'Interview scheduled.',201);
}
if ($action==='interview-result') {
    $id=id($b['interview_id']??null); $result=choice($b['result']??null,['Pass','Fail','Hold'],'result'); $notes=textValue($b['notes']??null,'notes',5000);
    transaction(function() use($id,$result,$notes) { $i=record('interviews','interview_id',$id,true); if (!isAdminOrHR() && (int)$i['interviewer_employee_id']!==ownEmployee()) fail(403,'You are not this interviewer.'); $a=record('job_applications','application_id',(int)$i['application_id']); vacancyAccess(record('job_vacancies','vacancy_id',(int)$a['vacancy_id']));
        if ($i['status']!=='Scheduled' || $a['status']!=='Interview') fail(409,'Interview is no longer pending.');
        if ($i['scheduled_at']>date('Y-m-d H:i:s')) fail(409,'An interview cannot be completed before its scheduled time.');
        query("UPDATE interviews SET status='Completed',result=?,notes=? WHERE interview_id=?",[$result,$notes,$id]); audit('recruitment.interview-result','job_applications',(int)$i['application_id'],['interview_id'=>$id,'result'=>$result]);
    }); reply(['interview_id'=>$id]);
}

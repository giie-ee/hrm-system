<?php
$b=HRMS_METHOD==='POST' ? input() : $_GET;
function contactFields(array $b): array {
    $email=textValue($b['email']??null,'email',100);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail(400,'Invalid email.');
    $phone=textValue($b['phone']??'','phone',30,false);
    if ($phone!=='' && !preg_match('/^\+?[0-9 ()-]{7,30}$/D',$phone)) fail(400,'Invalid phone number.');
    return [$email,$phone,textValue($b['address']??'','address',3000,false)];
}
function positionDepartment(int $position,int $department): void {
    $p=record('positions','position_id',$position);
    if ((int)$p['department_id']!==$department) fail(400,'Position does not belong to the selected department.');
}
if ($action==='get') {
    $params=[]; $where=scope('e.employee_id',$params);
    if (isset($b['employee_id'])) { $id=id($b['employee_id']); requireEmployeeAccess($id); $where.=' AND e.employee_id=?'; $params[]=$id; }
    if (isset($b['search'])) { $search=textValue($b['search'],'search',100); $where.=' AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_number LIKE ?)'; array_push($params,"%$search%","%$search%","%$search%"); }
    if (isset($b['department_id'])) { $where.=' AND e.department_id=?'; $params[]=id($b['department_id']); }
    if (isset($b['status'])) { $where.=' AND e.employment_status=?'; $params[]=choice($b['status'],['Active','Inactive','Suspended','Terminated']); }
    $fields=isAdminOrHR() ? 'e.*' : 'e.employee_id,e.employee_number,e.first_name,e.last_name,e.email,e.department_id,e.position_id,e.employment_type,e.employment_status';
    reply(rows("SELECT $fields,d.department_name,p.position_name FROM employees e JOIN departments d ON d.department_id=e.department_id JOIN positions p ON p.position_id=e.position_id WHERE $where ORDER BY e.employee_id".pageLimit(),$params));
}
if ($action==='update-profile') {
    $id=ownEmployee(); [$email,$phone,$address]=contactFields($b);
    transaction(function() use($id,$email,$phone,$address) { query('UPDATE employees SET email=?,phone=?,address=? WHERE employee_id=?',[$email,$phone,$address,$id]); audit('employee.self-update','employees',$id,['fields'=>['email','phone','address']]); }); reply(['employee_id'=>$id]);
}
requireRole(['Admin','HR']);
if ($action==='history') { $id=id($b['employee_id']??null); record('employees','employee_id',$id); reply(rows("SELECT action,metadata,created_at,user_id FROM audit_logs WHERE entity='employees' AND entity_id=? ORDER BY audit_id DESC".pageLimit(),[$id])); }
if ($action==='assign-manager') {
    $manager=id($b['manager_employee_id']??null); $employee=id($b['employee_id']??null); $status=choice($b['status']??'Active',['Active','Inactive']);
    if ($manager===$employee) fail(400,'An employee cannot manage themselves.');
    if (!one("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id JOIN employees e ON e.employee_id=u.employee_id WHERE u.employee_id=? AND r.role_name='Manager' AND u.account_status='Active' AND e.employment_status='Active'",[$manager])) fail(400,'An active Manager account is required.');
    record('employees','employee_id',$employee);
    transaction(function() use($manager,$employee,$status) { query('INSERT INTO manager_assignments(manager_employee_id,employee_id,status) VALUES (?,?,?) ON CONFLICT (manager_employee_id,employee_id) DO UPDATE SET status=EXCLUDED.status',[$manager,$employee,$status]); audit('employee.manager-assigned','employees',$employee,['manager_employee_id'=>$manager,'status'=>$status]); }); reply(['employee_id'=>$employee,'manager_employee_id'=>$manager,'status'=>$status]);
}
if ($action==='salary') {
    $id=id($b['employee_id']??null); $amount=money($b['basic_salary']??null,'basic_salary'); $from=dateValue($b['effective_from']??null); $to=isset($b['effective_to'])?dateValue($b['effective_to']):null;
    if ($to!==null && $to<$from) fail(400,'Invalid salary date range.');
    $salary=transaction(function() use($id,$amount,$from,$to) { record('employees','employee_id',$id,true);
        if (one('SELECT salary_id FROM employee_salaries WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?)',[$id,$to??'2100-12-31',$from])) fail(409,'Salary periods overlap. End the existing period before adding another.');
        query('INSERT INTO employee_salaries(employee_id,basic_salary,effective_from,effective_to) VALUES (?,?,?,?)',[$id,$amount,$from,$to]); $sid=inserted(); audit('employee.salary-created','employees',$id,['salary_id'=>$sid]); return $sid;
    }); reply(['salary_id'=>$salary],'Salary created.',201);
}
if ($action==='end-salary') {
    $sid=id($b['salary_id']??null); $to=dateValue($b['effective_to']??null);
    transaction(function() use($sid,$to) { $s=record('employee_salaries','salary_id',$sid,true); if ($to<$s['effective_from'] || $s['effective_to']!==null) fail(409,'Salary is already ended or the date is invalid.'); query("UPDATE employee_salaries SET effective_to=?,salary_status='Inactive' WHERE salary_id=?",[$to,$sid]); audit('employee.salary-ended','employees',(int)$s['employee_id'],['salary_id'=>$sid]); }); reply(['salary_id'=>$sid]);
}
if ($action==='status') {
    $id=id($b['employee_id']??null); $status=choice($b['employment_status']??null,['Active','Inactive','Suspended','Terminated']);
    if ($id===ownEmployee()) fail(409,'You cannot change your own employment status.');
    transaction(function() use($id,$status) { record('employees','employee_id',$id,true); if ($_SESSION['role_name']==='HR' && one("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE employee_id=? AND r.role_name='Admin'",[$id])) fail(403,'Only Admin may modify an administrator.'); query('UPDATE employees SET employment_status=? WHERE employee_id=?',[$status,$id]); query('UPDATE users SET auth_version=auth_version+1 WHERE employee_id=?',[$id]); audit('employee.status','employees',$id,['status'=>$status]); }); reply(['employee_id'=>$id,'employment_status'=>$status]);
}
if (!in_array($action,['create','update'],true)) fail(404,'Unknown employee action.');
$id=$action==='update'?id($b['employee_id']??null):null;
if ($id) { record('employees','employee_id',$id); if ($_SESSION['role_name']==='HR' && one("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE employee_id=? AND r.role_name='Admin'",[$id])) fail(403,'Only Admin may modify an administrator.'); }
$number=textValue($b['employee_number']??null,'employee_number',20); $first=textValue($b['first_name']??null,'first_name',50); $last=textValue($b['last_name']??null,'last_name',50); $middle=textValue($b['middle_name']??'','middle_name',50,false);
$gender=choice($b['gender']??null,['Male','Female','Other'],'gender'); [$email,$phone,$address]=contactFields($b);
$department=id($b['department_id']??null); $position=id($b['position_id']??null); positionDepartment($position,$department);
$type=choice($b['employment_type']??'Full-Time',['Full-Time','Part-Time','Contract','Temporary'],'employment_type'); $hire=dateValue($b['hire_date']??null,'hire_date');
$dob=$b['date_of_birth']??null;
if ($dob!==null) { if (!is_string($dob)) fail(400,'Invalid date_of_birth.'); $d=DateTimeImmutable::createFromFormat('!Y-m-d',$dob); if (!$d || $d->format('Y-m-d')!==$dob || $dob>date('Y-m-d') || $dob>=$hire || $dob<'1900-01-01') fail(400,'Invalid date_of_birth.'); }
$national=isset($b['national_id'])?textValue($b['national_id'],'national_id',50):null;
$id=transaction(function() use($id,$number,$first,$middle,$last,$gender,$dob,$national,$email,$phone,$address,$department,$position,$type,$hire,$action) {
    $values=[$number,$first,$middle,$last,$gender,$dob,$national,$email,$phone,$address,$department,$position,$type,$hire];
    if ($id) { $values[]=$id; query('UPDATE employees SET employee_number=?,first_name=?,middle_name=?,last_name=?,gender=?,date_of_birth=?,national_id=?,email=?,phone=?,address=?,department_id=?,position_id=?,employment_type=?,hire_date=? WHERE employee_id=?',$values); }
    else { query('INSERT INTO employees(employee_number,first_name,middle_name,last_name,gender,date_of_birth,national_id,email,phone,address,department_id,position_id,employment_type,hire_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$values); $id=inserted(); }
    audit('employee.'.$action,'employees',$id,['fields'=>['identity','contact','employment']]); return $id;
}); reply(['employee_id'=>$id], 'Employee saved.', $action==='create'?201:200);

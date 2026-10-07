<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
function onboardingAccess(array $o): void {
    if (!isAdminOrHR() && (int)$o['employee_id']!==ownEmployee()) fail(403,'Only the employee and HR/Admin may access onboarding documents.');
}
function privateStorage(): string {
    $path=getenv('HRMS_DOCUMENT_DIR') ?: dirname(__DIR__,3).'/hrms-private';
    if (!is_dir($path) && !mkdir($path,0700,true)) fail(500,'Private storage is unavailable.');
    $real=realpath($path); $web=realpath($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__,2));
    if (!$real || ($web && str_starts_with(strtolower(str_replace('\\','/',$real)).'/',strtolower(str_replace('\\','/',$web)).'/'))) fail(500,'Document storage must be outside the web root.');
    return $real;
}
if ($action==='get') {
    $params=[]; $where=isAdminOrHR()?'1=1':'employee_id=?'; if (!isAdminOrHR()) $params[]=ownEmployee();
    if (isset($b['employee_id'])) { $employee=id($b['employee_id']); if (!isAdminOrHR() && $employee!==ownEmployee()) fail(403,'Access denied.'); $where.=' AND employee_id=?'; $params[]=$employee; }
    $onboardings=rows("SELECT * FROM onboarding WHERE $where ORDER BY onboarding_id DESC".pageLimit(),$params);
    foreach ($onboardings as &$o) {
        $o['documents']=rows('SELECT document_id,document_name,document_type,document_status,rejection_reason,verified_by,verified_at FROM onboarding_documents WHERE onboarding_id=?',[(int)$o['onboarding_id']]);
        $verified=count(array_filter($o['documents'],fn($d)=>$d['document_status']==='Verified')); $o['verification_percentage']=count($o['documents'])?round(100*$verified/count($o['documents']),2):0;
    } unset($o); reply($onboardings);
}
if ($action==='create' || $action==='update') {
    requireRole(['Admin','HR']); $id=$action==='update'?id($b['onboarding_id']??null):null;
    $id=transaction(function() use($id,$b) {
        $old=$id?record('onboarding','onboarding_id',$id,true):[]; $employee=id($old['employee_id']??$b['employee_id']??null); record('employees','employee_id',$employee,true);
        if ($id && $old['onboarding_status']==='Completed') fail(409,'Completed onboarding is immutable.');
        if (!$id && one("SELECT onboarding_id FROM onboarding WHERE employee_id=? AND onboarding_status<>'Completed'",[$employee])) fail(409,'An open onboarding record exists.');
        $hr=id($b['assigned_hr_id']??$old['assigned_hr_id']??null); if (!one("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=? AND r.role_name IN ('Admin','HR') AND u.account_status='Active'",[$hr])) fail(400,'assigned_hr_id must identify an active HR/Admin user.');
        $start=dateValue($b['start_date']??$old['start_date']??null); $status=choice($b['onboarding_status']??$old['onboarding_status']??'Not Started',['Not Started','In Progress','Completed','On Hold']); $notes=textValue($b['notes']??$old['notes']??'','notes',5000,false);
        if (!$id && !in_array($status,['Not Started','In Progress'],true)) fail(400,'New onboarding starts Not Started or In Progress.');
        if ($id && $status!==$old['onboarding_status'] && !in_array($status,['Not Started'=>['In Progress','On Hold'],'In Progress'=>['On Hold','Completed'],'On Hold'=>['In Progress']][$old['onboarding_status']]??[],true)) fail(409,'Invalid onboarding transition.');
        if ($status==='Completed') { $n=one("SELECT COUNT(*) total,SUM(CASE WHEN document_status='Verified' THEN 1 ELSE 0 END) verified FROM onboarding_documents WHERE onboarding_id=?",[$id]); if (!(int)$n['total'] || (int)$n['total']!==(int)$n['verified']) fail(409,'All required documents must be verified.'); }
        if ($id) query('UPDATE onboarding SET start_date=?,onboarding_status=?,assigned_hr_id=?,notes=?,completed_at=? WHERE onboarding_id=?',[$start,$status,$hr,$notes,$status==='Completed'?date('Y-m-d H:i:s'):null,$id]);
        else { query('INSERT INTO onboarding(employee_id,start_date,onboarding_status,assigned_hr_id,notes) VALUES (?,?,?,?,?)',[$employee,$start,$status,$hr,$notes]); $id=inserted(); }
        audit('onboarding.saved','onboarding',$id,['status'=>$status]); return $id;
    }); reply(record('onboarding','onboarding_id',$id),'Onboarding saved.',$action==='create'?201:200);
}
function documentCatalog(): array {
    // [document name, document type] - drives the "request a document" dropdowns.
    return [['National ID / NRC copy','Identification'],['Passport photo','Identification'],['Proof of address','Identification'],
        ['Bank account details','Financial'],['Tax (TPIN) certificate','Financial'],['NAPSA / social security card','Financial'],
        ['Academic certificates','Qualification'],['Professional certificates','Qualification'],
        ['Curriculum vitae (CV)','Employment'],['Reference letter','Employment'],['Previous employment letter','Employment'],
        ['Signed employment contract','Legal'],['Police clearance','Legal'],['Medical certificate','Medical']];
}
function documentTypes(): array { return ['Identification','Financial','Qualification','Employment','Legal','Medical','Other']; }
if ($action==='document-types') {
    requireLogin();
    reply(['types'=>documentTypes(),'documents'=>array_map(fn($d)=>['document_name'=>$d[0],'document_type'=>$d[1]],documentCatalog())]);
}
if ($action==='request-document') {
    requireRole(['Admin','HR']); $oid=id($b['onboarding_id']??null); $name=textValue($b['document_name']??null,'document_name',150); $type=choice($b['document_type']??'Other',documentTypes(),'document_type');
    $id=transaction(function() use($oid,$name,$type) { $o=record('onboarding','onboarding_id',$oid,true); if ($o['onboarding_status']==='Completed') fail(409,'Onboarding is completed.'); if (one('SELECT document_id FROM onboarding_documents WHERE onboarding_id=? AND document_name=?',[$oid,$name])) fail(409,'Document request already exists.');
        query('INSERT INTO onboarding_documents(onboarding_id,document_name,document_type) VALUES (?,?,?)',[$oid,$name,$type]); $id=inserted(); audit('document.requested','onboarding_documents',$id); notifyEmployee((int)$o['employee_id'],'Onboarding document requested','onboarding_documents',$id); return $id;
    }); reply(['document_id'=>$id],'Document requested.',201);
}
function onboardingFormFields(): array {
    // field => [required, max length]
    return ['phone'=>[true,30],'address'=>[true,300],'emergency_contact_name'=>[true,100],'emergency_contact_phone'=>[true,30],
        'emergency_contact_relationship'=>[true,50],'bank_name'=>[true,100],'bank_account_number'=>[true,40],
        'tax_number'=>[false,30],'national_id'=>[false,50],'next_of_kin_name'=>[false,100]];
}
if ($action==='form-get') {
    requireLogin(); $params=[]; $where=scope('o.employee_id',$params);
    if (isset($b['employee_id']) && $b['employee_id']!=='') { $employee=id($b['employee_id'],'employee_id'); requireEmployeeAccess($employee); $where.=' AND o.employee_id=?'; $params[]=$employee; }
    if (isset($b['status']) && $b['status']!=='') { $where.=" AND COALESCE(f.status,'Pending')=?"; $params[]=choice($b['status'],['Pending','Submitted','Approved','Rejected'],'status'); }
    $forms=rows("SELECT o.onboarding_id,o.employee_id,CONCAT(e.first_name,' ',e.last_name) employee_name,o.onboarding_status,f.form_id,"
        ."COALESCE(f.status,'Pending') form_status,f.form_data,f.submitted_at,f.reviewed_by,f.reviewed_at,f.rejection_reason "
        ."FROM onboarding o JOIN employees e ON e.employee_id=o.employee_id LEFT JOIN onboarding_forms f ON f.onboarding_id=o.onboarding_id "
        ."WHERE $where ORDER BY o.onboarding_id DESC".pageLimit(),$params);
    foreach ($forms as &$f) { $f['form_data']=$f['form_data']!==null?(json_decode((string)$f['form_data'],true)?:new stdClass()):new stdClass(); } unset($f);
    reply(['fields'=>array_map(fn($rule)=>['required'=>$rule[0],'max_length'=>$rule[1]],onboardingFormFields()),'forms'=>$forms]);
}
if ($action==='form-submit') {
    requireLogin(); $employee=ownEmployee();
    $o=one("SELECT * FROM onboarding WHERE employee_id=? AND onboarding_status<>'Completed' ORDER BY onboarding_id DESC LIMIT 1",[$employee])
        ?? fail(404,'No open onboarding record exists. Ask HR to start your onboarding.');
    $data=[]; foreach (onboardingFormFields() as $field=>[$required,$max]) { $data[$field]=textValue($b[$field]??'',$field,$max,$required); }
    foreach (['phone','emergency_contact_phone'] as $field) { if (!preg_match('/^[0-9+()\- ]{7,30}$/D',$data[$field])) fail(400,"{$field} is not a valid phone number."); }
    if (!preg_match('/^[0-9A-Za-z\- ]{4,40}$/D',$data['bank_account_number'])) fail(400,'bank_account_number is not valid.');
    $formId=transaction(function() use($o,$employee,$data) {
        $onboarding=record('onboarding','onboarding_id',(int)$o['onboarding_id'],true);
        if ($onboarding['onboarding_status']==='Completed') fail(409,'Onboarding is completed.');
        $existing=one('SELECT form_id,status FROM onboarding_forms WHERE onboarding_id=? FOR UPDATE',[(int)$o['onboarding_id']]);
        if ($existing && in_array($existing['status'],['Submitted','Approved'],true)) fail(409,'This form is already '.strtolower($existing['status']).' and cannot be changed.');
        query("INSERT INTO onboarding_forms(onboarding_id,employee_id,form_data,status,submitted_at) VALUES (?,?,?,'Submitted',NOW()) "
            ."ON CONFLICT (onboarding_id) DO UPDATE SET form_data=EXCLUDED.form_data,status='Submitted',submitted_at=NOW(),reviewed_by=NULL,reviewed_at=NULL,rejection_reason=NULL",
            [(int)$o['onboarding_id'],$employee,json_encode($data,JSON_THROW_ON_ERROR)]);
        $id=$existing?(int)$existing['form_id']:inserted();
        audit('onboarding.form-submitted','onboarding_forms',$id);
        notifyHR('Onboarding form submitted','onboarding_forms',$id);
        query("INSERT INTO notifications (user_id,title,entity,entity_id) SELECT u.user_id,?,?,? FROM manager_assignments ma JOIN users u ON u.employee_id=ma.manager_employee_id "
            ."WHERE ma.employee_id=? AND ma.status='Active' AND u.account_status='Active'",['Onboarding form submitted','onboarding_forms',$id,$employee]);
        return $id;
    });
    reply(['form_id'=>$formId,'form_status'=>'Submitted'],'Onboarding form submitted for approval.',201);
}
if ($action==='form-review') {
    requireRole(['Admin','HR','Manager']);
    $formId=id($b['form_id']??null,'form_id'); $decision=choice($b['status']??null,['Approved','Rejected']);
    $reason=textValue($b['reason']??'','reason',5000,$decision==='Rejected');
    $form=record('onboarding_forms','form_id',$formId); requireReviewer((int)$form['employee_id']);
    transaction(function() use($formId,$decision,$reason) {
        $f=record('onboarding_forms','form_id',$formId,true);
        if ($f['status']!=='Submitted') fail(409,'Only submitted forms can be reviewed.');
        query('UPDATE onboarding_forms SET status=?,reviewed_by=?,reviewed_at=NOW(),rejection_reason=? WHERE form_id=?',[$decision,(int)$_SESSION['user_id'],$decision==='Rejected'?$reason:null,$formId]);
        if ($decision==='Approved') {
            $data=json_decode((string)$f['form_data'],true)?:[]; $employee=(int)$f['employee_id'];
            query('UPDATE employees SET phone=?,address=? WHERE employee_id=?',[(string)($data['phone']??''),(string)($data['address']??''),$employee]);
            if (!empty($data['national_id'])) query('UPDATE employees SET national_id=? WHERE employee_id=? AND national_id IS NULL AND NOT EXISTS (SELECT 1 FROM employees x WHERE x.national_id=?)',[$data['national_id'],$employee,$data['national_id']]);
        }
        audit('onboarding.form-'.strtolower($decision),'onboarding_forms',$formId); notifyEmployee((int)$f['employee_id'],'Onboarding form '.strtolower($decision),'onboarding_forms',$formId);
    });
    reply(['form_id'=>$formId,'form_status'=>$decision],'Onboarding form '.strtolower($decision).'.');
}
$id=id($b['document_id']??null); $doc=record('onboarding_documents','document_id',$id); $o=record('onboarding','onboarding_id',(int)$doc['onboarding_id']); onboardingAccess($o);
if ($action==='history') reply(rows('SELECT status,reason,changed_by,created_at FROM document_history WHERE document_id=? ORDER BY history_id'.pageLimit(),[$id]));
if ($action==='download') {
    if (!$doc['document_path'] || !preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D',$doc['document_path'])) fail(404,'A private document is not available.');
    $path=privateStorage().DIRECTORY_SEPARATOR.$doc['document_path']; if (!is_file($path)) fail(404,'Document not found.');
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="document-'.$id.'.'.pathinfo($path,PATHINFO_EXTENSION).'"'); header('Content-Length: '.filesize($path)); readfile($path); exit;
}
if ($action==='submit') {
    $upload=$_FILES['file']??null;
    if (!$upload || !is_array($upload) || ($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($upload['tmp_name']??null) || !is_uploaded_file($upload['tmp_name']) || $upload['size']>5*1024*1024 || $upload['size']<=0) fail(400,'Upload one PDF, PNG or JPEG file up to 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']); $ext=['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg'][$mime]??null;
    if (!$ext) fail(400,'Unsupported document type.');
    $token=bin2hex(random_bytes(24)).'.'.$ext; $path=privateStorage().DIRECTORY_SEPARATOR.$token;
    try {
        transaction(function() use($id,$upload,$token,$path,$o) {
            $onboarding=record('onboarding','onboarding_id',(int)$o['onboarding_id'],true); $d=record('onboarding_documents','document_id',$id,true);
            if ($onboarding['onboarding_status']==='Completed' || !in_array($d['document_status'],['Pending','Rejected'],true)) fail(409,'Only Pending or Rejected documents can be submitted.');
            if (!move_uploaded_file($upload['tmp_name'],$path)) fail(500,'Unable to store document.');
            query("UPDATE onboarding_documents SET document_path=?,document_status='Submitted',rejection_reason=NULL,verified_by=NULL,verified_at=NULL WHERE document_id=?",[$token,$id]);
            query("INSERT INTO document_history(document_id,status,changed_by) VALUES (?,'Submitted',?)",[$id,(int)$_SESSION['user_id']]); audit('document.submitted','onboarding_documents',$id); notifyHR('Onboarding document submitted','onboarding_documents',$id);
        });
    } catch(Throwable $e) { if (is_file($path)) unlink($path); throw $e; }
    reply(['document_id'=>$id,'document_status'=>'Submitted']);
}
if ($action==='verify') {
    requireRole(['Admin','HR']); $status=choice($b['document_status']??null,['Verified','Rejected']); $reason=textValue($b['reason']??'','reason',5000,$status==='Rejected');
    transaction(function() use($id,$status,$reason,$o) {
        record('onboarding','onboarding_id',(int)$o['onboarding_id'],true); $d=record('onboarding_documents','document_id',$id,true); if ($d['document_status']!=='Submitted') fail(409,'Only Submitted documents can be reviewed.');
        if ((int)$o['employee_id']===ownEmployee()) fail(403,'You cannot verify your own document.');
        query('UPDATE onboarding_documents SET document_status=?,rejection_reason=?,verified_by=?,verified_at=NOW() WHERE document_id=?',[$status,$status==='Rejected'?$reason:null,(int)$_SESSION['user_id'],$id]);
        query('INSERT INTO document_history(document_id,status,reason,changed_by) VALUES (?,?,?,?)',[$id,$status,$reason,(int)$_SESSION['user_id']]); audit('document.'.$status,'onboarding_documents',$id); notifyEmployee((int)$o['employee_id'],'Document '.$status,'onboarding_documents',$id);
    }); reply(['document_id'=>$id,'document_status'=>$status]);
}

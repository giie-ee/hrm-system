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
if ($action==='request-document') {
    requireRole(['Admin','HR']); $oid=id($b['onboarding_id']??null); $name=textValue($b['document_name']??null,'document_name',150); $type=textValue($b['document_type']??'','document_type',100,false);
    $id=transaction(function() use($oid,$name,$type) { $o=record('onboarding','onboarding_id',$oid,true); if ($o['onboarding_status']==='Completed') fail(409,'Onboarding is completed.'); if (one('SELECT document_id FROM onboarding_documents WHERE onboarding_id=? AND document_name=?',[$oid,$name])) fail(409,'Document request already exists.');
        query('INSERT INTO onboarding_documents(onboarding_id,document_name,document_type) VALUES (?,?,?)',[$oid,$name,$type]); $id=inserted(); audit('document.requested','onboarding_documents',$id); notifyEmployee((int)$o['employee_id'],'Onboarding document requested','onboarding_documents',$id); return $id;
    }); reply(['document_id'=>$id],'Document requested.',201);
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

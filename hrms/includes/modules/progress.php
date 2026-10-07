<?php
// Progress tracker: HR/Admin/Manager create records with 2+ milestones for an
// employee and tick the milestones off; employees can read their own records.
$b=HRMS_METHOD==='POST'?input():$_GET;

function progressMilestoneTitles(mixed $value): array {
    if (!is_array($value) || !array_is_list($value)) fail(400,'milestones must be a list of titles.');
    $titles=[]; foreach ($value as $title) { $titles[]=textValue(is_array($title)?($title['title']??null):$title,'milestone title',150); }
    if (count($titles)<2) fail(400,'A progress record needs at least 2 milestones.');
    if (count($titles)>20) fail(400,'A progress record can have at most 20 milestones.');
    return $titles;
}
function progressWithMilestones(array $records): array {
    foreach ($records as &$r) {
        $r['milestones']=rows('SELECT milestone_id,title,sort_order,is_complete,completed_at,completed_by FROM progress_milestones WHERE record_id=? ORDER BY sort_order,milestone_id',[(int)$r['record_id']]);
        $done=count(array_filter($r['milestones'],fn($m)=>filter_var($m['is_complete'],FILTER_VALIDATE_BOOLEAN)));
        $r['milestones_total']=count($r['milestones']); $r['milestones_completed']=$done;
        $r['progress_percentage']=$r['milestones_total']?round(100*$done/$r['milestones_total'],2):0;
    } unset($r);
    return $records;
}
function progressSyncStatus(int $recordId): void {
    $n=one('SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN is_complete THEN 1 ELSE 0 END),0) done FROM progress_milestones WHERE record_id=?',[$recordId]);
    $complete=(int)$n['total']>0 && (int)$n['total']===(int)$n['done'];
    query("UPDATE progress_records SET status=?,completed_at=? WHERE record_id=? AND status<>'Cancelled'",
        [$complete?'Completed':'In Progress',$complete?date('Y-m-d H:i:s'):null,$recordId]);
}

if ($action==='get') {
    requireLogin(); $params=[]; $where=scope('p.employee_id',$params);
    if (isset($b['employee_id']) && $b['employee_id']!=='') { $employee=id($b['employee_id'],'employee_id'); requireEmployeeAccess($employee); $where.=' AND p.employee_id=?'; $params[]=$employee; }
    if (isset($b['status']) && $b['status']!=='') { $where.=' AND p.status=?'; $params[]=choice($b['status'],['In Progress','Completed','Cancelled'],'status'); }
    reply(progressWithMilestones(rows("SELECT p.*,CONCAT(e.first_name,' ',e.last_name) employee_name FROM progress_records p JOIN employees e ON e.employee_id=p.employee_id WHERE $where ORDER BY p.record_id DESC".pageLimit(),$params)));
}

requireRole(['Admin','HR','Manager']);

if ($action==='create') {
    $employee=id($b['employee_id']??null,'employee_id'); requireReviewer($employee);
    $title=textValue($b['title']??null,'title',150); $description=textValue($b['description']??'','description',5000,false);
    $due=isset($b['due_date']) && $b['due_date']!==''?dateValue($b['due_date'],'due_date'):null; $titles=progressMilestoneTitles($b['milestones']??null);
    $id=transaction(function() use($employee,$title,$description,$due,$titles) {
        $target=record('employees','employee_id',$employee,true); if ($target['employment_status']!=='Active') fail(409,'Progress can only be tracked for active employees.');
        query('INSERT INTO progress_records(employee_id,title,description,due_date,created_by) VALUES (?,?,?,?,?)',[$employee,$title,$description!==''?$description:null,$due,(int)$_SESSION['user_id']]); $id=inserted();
        foreach ($titles as $i=>$t) query('INSERT INTO progress_milestones(record_id,title,sort_order) VALUES (?,?,?)',[$id,$t,$i+1]);
        audit('progress.created','progress_records',$id,['employee_id'=>$employee,'milestones'=>count($titles)]); notifyEmployee($employee,'A progress record was created for you','progress_records',$id); return $id;
    });
    reply(progressWithMilestones([record('progress_records','record_id',$id)])[0],'Progress record created.',201);
}

$recordId=id($b['record_id']??null,'record_id'); $existing=record('progress_records','record_id',$recordId); requireReviewer((int)$existing['employee_id']);

if ($action==='update') {
    $title=textValue($b['title']??$existing['title'],'title',150); $description=textValue($b['description']??($existing['description']??''),'description',5000,false);
    $due=array_key_exists('due_date',$b)?($b['due_date']!==null&&$b['due_date']!==''?dateValue($b['due_date'],'due_date'):null):$existing['due_date'];
    $cancel=isset($b['status'])?choice($b['status'],['In Progress','Cancelled'],'status'):null;
    transaction(function() use($recordId,$title,$description,$due,$cancel,$existing) {
        record('progress_records','record_id',$recordId,true); if ($existing['status']==='Completed') fail(409,'A completed record cannot be changed.');
        query('UPDATE progress_records SET title=?,description=?,due_date=? WHERE record_id=?',[$title,$description!==''?$description:null,$due,$recordId]);
        if ($cancel==='Cancelled') query("UPDATE progress_records SET status='Cancelled' WHERE record_id=?",[$recordId]);
        elseif ($cancel==='In Progress' && $existing['status']==='Cancelled') query("UPDATE progress_records SET status='In Progress' WHERE record_id=?",[$recordId]);
        audit('progress.updated','progress_records',$recordId,['status'=>$cancel]);
    });
    reply(progressWithMilestones([record('progress_records','record_id',$recordId)])[0],'Progress record updated.');
}
if ($action==='add-milestone') {
    $title=textValue($b['title']??null,'title',150);
    transaction(function() use($recordId,$title,$existing) {
        record('progress_records','record_id',$recordId,true); if ($existing['status']!=='In Progress') fail(409,'Milestones can only be added to records in progress.');
        $n=one('SELECT COUNT(*) total,COALESCE(MAX(sort_order),0) last FROM progress_milestones WHERE record_id=?',[$recordId]); if ((int)$n['total']>=20) fail(409,'A progress record can have at most 20 milestones.');
        query('INSERT INTO progress_milestones(record_id,title,sort_order) VALUES (?,?,?)',[$recordId,$title,(int)$n['last']+1]); $mid=inserted(); progressSyncStatus($recordId); audit('progress.milestone-added','progress_milestones',$mid);
    });
    reply(progressWithMilestones([record('progress_records','record_id',$recordId)])[0],'Milestone added.',201);
}
if ($action==='complete-milestone') {
    $milestone=id($b['milestone_id']??null,'milestone_id'); $complete=array_key_exists('is_complete',$b)?filter_var($b['is_complete'],FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE):true;
    if ($complete===null) fail(400,'is_complete must be true or false.');
    transaction(function() use($recordId,$milestone,$complete,$existing) {
        record('progress_records','record_id',$recordId,true); if ($existing['status']==='Cancelled') fail(409,'A cancelled record cannot be changed.');
        $m=record('progress_milestones','milestone_id',$milestone,true); if ((int)$m['record_id']!==$recordId) fail(404,'Milestone does not belong to this record.');
        query('UPDATE progress_milestones SET is_complete=?,completed_at=?,completed_by=? WHERE milestone_id=?',
            [$complete?'true':'false',$complete?date('Y-m-d H:i:s'):null,$complete?(int)$_SESSION['user_id']:null,$milestone]);
        progressSyncStatus($recordId); audit($complete?'progress.milestone-completed':'progress.milestone-reopened','progress_milestones',$milestone);
        if ($complete) notifyEmployee((int)$existing['employee_id'],'A progress milestone was completed','progress_records',$recordId);
    });
    reply(progressWithMilestones([record('progress_records','record_id',$recordId)])[0],$complete?'Milestone completed.':'Milestone reopened.');
}
fail(404,'Unknown progress action.');

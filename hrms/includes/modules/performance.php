<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
function activeCycle(int $cycle, bool $allowDraft=false): array {
    $c=record('performance_cycles','cycle_id',$cycle,true);
    if (!in_array($c['status'],$allowDraft?['Draft','Active']:['Active'],true)) fail(409,'The performance cycle is not open for this action.');
    return $c;
}
function reviewAccess(array $r): void { requireEmployeeAccess((int)$r['employee_id']); }
function reviewerAccess(array $r): void {
    requireReviewer((int)$r['employee_id']);
    if ($_SESSION['role_name']==='Manager' && (int)$r['reviewer_employee_id']!==ownEmployee()) fail(403,'You are not the assigned reviewer.');
}
if ($section==='cycles' && $action==='update') {
    requireRole(['Admin','HR']); $id=id($b['cycle_id']??null);
    transaction(function() use($id,$b) {
        $c=record('performance_cycles','cycle_id',$id,true);
        if ($c['status']==='Archived') fail(409,'Archived cycles are immutable.');
        $status=choice($b['status']??$c['status'],['Draft','Active','Closed','Archived']);
        $next=['Draft'=>['Draft','Active'],'Active'=>['Active','Closed'],'Closed'=>['Closed','Archived']];
        if (!in_array($status,$next[$c['status']],true)) fail(409,'Invalid cycle transition.');
        if ($status==='Closed' && one("SELECT review_id FROM performance_reviews WHERE cycle_id=? AND review_status NOT IN ('Finalized','Closed')",[$id])) fail(409,'Finalize all reviews before closing a cycle.');
        $name=textValue($b['cycle_name']??$c['cycle_name'],'cycle_name',150); $desc=textValue($b['description']??$c['description']??'','description',5000,false);
        [$start,$end]=dates(['start_date'=>$b['start_date']??$c['start_date'],'end_date'=>$b['end_date']??$c['end_date']]);
        if (($start!==$c['start_date'] || $end!==$c['end_date']) && one('SELECT goal_id FROM performance_goals WHERE cycle_id=?',[$id])) fail(409,'Cycle dates are locked once goals exist.');
        if (one('SELECT cycle_id FROM performance_cycles WHERE cycle_name=? AND cycle_id<>?',[$name,$id])) fail(409,'Cycle name already exists.');
        query('UPDATE performance_cycles SET cycle_name=?,description=?,start_date=?,end_date=?,status=? WHERE cycle_id=?',[$name,$desc,$start,$end,$status,$id]); audit('performance.cycle-updated','performance_cycles',$id,['status'=>$status]);
    }); reply(record('performance_cycles','cycle_id',$id));
}
if ($section==='goals' && $action==='get') {
    $params=[]; $where=scope('g.employee_id',$params);
    foreach (['employee_id','cycle_id'] as $f) if (isset($b[$f])) { $where.=" AND g.$f=?"; $params[]=id($b[$f]); if ($f==='employee_id') requireEmployeeAccess(id($b[$f])); }
    reply(rows("SELECT g.* FROM performance_goals g WHERE $where ORDER BY g.goal_id".pageLimit(),$params));
}
if ($section==='goals') {
    $result=transaction(function() use($action,$b) {
        if ($action==='create') { requireRole(['Admin','HR','Manager']); $employee=id($b['employee_id']??null); requireEmployeeAccess($employee); $cycle=id($b['cycle_id']??null); $id=null; $old=[]; }
        else { $id=id($b['goal_id']??null); $old=record('performance_goals','goal_id',$id); $employee=(int)$old['employee_id']; $cycle=(int)$old['cycle_id']; requireEmployeeAccess($employee); }
        $c=activeCycle($cycle, $action!=='progress'); record('employees','employee_id',$employee,true);
        if (one("SELECT review_id FROM performance_reviews WHERE employee_id=? AND cycle_id=? AND review_status IN ('Manager Review','Finalized','Closed')",[$employee,$cycle])) fail(409,'Goals are frozen after self-assessment submission.');
        if ($action==='progress') {
            if (!isAdminOrHR() && $employee!==ownEmployee()) fail(403,'Only the employee or HR can report goal progress.');
            $progress=number($b['progress']??null,0,100,'progress');
            if (($old['status']??'')==='Cancelled') fail(409,'Cancelled goals cannot be changed.');
            $status=$progress==100?'Completed':($progress>0?'In Progress':'Not Started');
            query('UPDATE performance_goals SET progress=?,status=? WHERE goal_id=?',[$progress,$status,$id]);
        } else {
            requireRole(['Admin','HR','Manager']);
            $title=textValue($b['goal_title']??$old['goal_title']??null,'goal_title',200); $description=textValue($b['goal_description']??$old['goal_description']??'','goal_description',5000,false);
            $kpi=textValue($b['kpi']??$old['kpi']??'','kpi',255,false); $target=textValue($b['target_value']??$old['target_value']??'','target_value',255,false);
            $weight=number($b['weight']??$old['weight']??null,0,100,'weight'); $due=dateValue($b['due_date']??$old['due_date']??$c['end_date'],'due_date');
            if ($due<$c['start_date'] || $due>$c['end_date']) fail(400,'Goal due date must be within the cycle.');
            $status=choice($b['status']??$old['status']??'Not Started',['Not Started','In Progress','Completed','Cancelled']);
            if ($action==='create' && $status!=='Not Started') fail(400,'New goals start as Not Started.');
            if ($action==='update' && $status!==$old['status'] && $status!=='Cancelled') fail(400,'Use progress to complete a goal.');
            $total=one("SELECT COALESCE(SUM(weight),0) total FROM performance_goals WHERE employee_id=? AND cycle_id=? AND goal_id<>? AND status<>'Cancelled'",[$employee,$cycle,$id??0]);
            if ($status!=='Cancelled' && (float)$total['total']+$weight>100.00001) fail(409,'Active goal weights cannot exceed 100%.');
            if ($id) query('UPDATE performance_goals SET goal_title=?,goal_description=?,kpi=?,target_value=?,weight=?,due_date=?,status=? WHERE goal_id=?',[$title,$description,$kpi,$target,$weight,$due,$status,$id]);
            else { query('INSERT INTO performance_goals(cycle_id,employee_id,goal_title,goal_description,kpi,target_value,weight,due_date,created_by) VALUES (?,?,?,?,?,?,?,?,?)',[$cycle,$employee,$title,$description,$kpi,$target,$weight,$due,(int)$_SESSION['user_id']]); $id=inserted(); }
        }
        audit('performance.goal-'.$action,'performance_goals',$id); return record('performance_goals','goal_id',$id);
    }); reply($result,'Goal saved.',$action==='create'?201:200);
}
if ($section==='reviews' && $action==='get') {
    $params=[]; $where=scope('r.employee_id',$params);
    foreach (['review_id','employee_id','cycle_id'] as $f) if (isset($b[$f])) { $where.=" AND r.$f=?"; $params[]=id($b[$f]); if ($f==='employee_id') requireEmployeeAccess(id($b[$f])); }
    $reviews=rows("SELECT r.* FROM performance_reviews r WHERE $where ORDER BY r.review_id DESC".pageLimit(),$params);
    foreach ($reviews as &$r) {
        if ($_SESSION['role_name']==='Employee' && !in_array($r['review_status'],['Finalized','Closed'],true)) foreach (['manager_comments','strengths','areas_for_improvement','development_plan','overall_rating','manager_submitted_at'] as $f) unset($r[$f]);
        else $r['goal_ratings']=rows('SELECT goal_id,rating,comments,rated_by FROM performance_goal_ratings WHERE review_id=?',[(int)$r['review_id']]);
    } unset($r); reply($reviews);
}
if ($section==='reviews') {
    $result=transaction(function() use($action,$b) {
        if ($action==='create') {
            requireRole(['Admin','HR','Manager']); $cycle=id($b['cycle_id']??null); $employee=id($b['employee_id']??null); requireEmployeeAccess($employee); activeCycle($cycle);
            $reviewer=$_SESSION['role_name']==='Manager'?ownEmployee():id($b['reviewer_employee_id']??null);
            if ($reviewer===$employee || !one("SELECT ma.assignment_id FROM manager_assignments ma JOIN users u ON u.employee_id=ma.manager_employee_id JOIN roles r ON r.role_id=u.role_id WHERE ma.manager_employee_id=? AND ma.employee_id=? AND ma.status='Active' AND r.role_name='Manager' AND u.account_status='Active'",[$reviewer,$employee])) fail(400,'Reviewer must be an assigned active manager other than the employee.');
            query('INSERT INTO performance_reviews(cycle_id,employee_id,reviewer_employee_id) VALUES (?,?,?)',[$cycle,$employee,$reviewer]); $id=inserted(); audit('performance.review-created','performance_reviews',$id); notifyEmployee($employee,'Performance review available','performance_reviews',$id); return record('performance_reviews','review_id',$id);
        }
        $id=id($b['review_id']??null); $initial=record('performance_reviews','review_id',$id); activeCycle((int)$initial['cycle_id']); $r=record('performance_reviews','review_id',$id,true); reviewAccess($r);
        if (in_array($r['review_status'],['Finalized','Closed'],true)) fail(409,'Finalized reviews are immutable.');
        if ($action==='start') {
            if (!isAdminOrHR() && ownEmployee()!==(int)$r['employee_id']) reviewerAccess($r);
            if ($r['review_status']!=='Not Started') fail(409,'Review has already started.');
            query("UPDATE performance_reviews SET review_status='Self-Assessment' WHERE review_id=?",[$id]);
        } elseif ($action==='self-assessment') {
            if (ownEmployee()!==(int)$r['employee_id']) fail(403,'Only the employee can submit their self-assessment.');
            if ($r['review_status']!=='Self-Assessment') fail(409,'Review is not awaiting self-assessment.');
            $assessment=textValue($b['self_assessment']??null,'self_assessment',10000);
            $weights=one("SELECT SUM(weight) total FROM performance_goals WHERE cycle_id=? AND employee_id=? AND status<>'Cancelled'",[(int)$r['cycle_id'],(int)$r['employee_id']]);
            if (abs((float)$weights['total']-100)>0.001) fail(409,'Goal weights must total 100% before submission.');
            query("UPDATE performance_reviews SET self_assessment=?,employee_submitted_at=NOW(),review_status='Manager Review' WHERE review_id=?",[$assessment,$id]); notifyEmployee((int)$r['reviewer_employee_id'],'Self-assessment ready for review','performance_reviews',$id);
        } elseif ($action==='evaluate') {
            reviewerAccess($r); if ($r['review_status']!=='Manager Review') fail(409,'Self-assessment must be submitted first.');
            $comments=textValue($b['manager_comments']??null,'manager_comments',10000); $strengths=textValue($b['strengths']??null,'strengths',5000); $areas=textValue($b['areas_for_improvement']??null,'areas_for_improvement',5000); $plan=textValue($b['development_plan']??null,'development_plan',5000);
            $ratings=$b['ratings']??null; if (!is_array($ratings) || !array_is_list($ratings) || !$ratings || count($ratings)>200) fail(400,'Provide a list of goal ratings.');
            $goals=rows("SELECT goal_id FROM performance_goals WHERE employee_id=? AND cycle_id=? AND status<>'Cancelled'",[(int)$r['employee_id'],(int)$r['cycle_id']]);
            $expected=array_map('intval',array_column($goals,'goal_id')); $seen=[];
            foreach ($ratings as $rating) {
                if (!is_array($rating)) fail(400,'Invalid rating item.'); $goal=id($rating['goal_id']??null);
                if (!in_array($goal,$expected,true) || in_array($goal,$seen,true)) fail(400,'Goal must belong to this employee/cycle and occur once.'); $seen[]=$goal;
                $value=number($rating['rating']??null,0,5,'rating'); $note=textValue($rating['comments']??'','comments',5000,false);
                query('INSERT INTO performance_goal_ratings(review_id,goal_id,rating,comments,rated_by) VALUES (?,?,?,?,?) ON CONFLICT (review_id,goal_id) DO UPDATE SET rating=EXCLUDED.rating,comments=EXCLUDED.comments,rated_by=EXCLUDED.rated_by',[$id,$goal,$value,$note,ownEmployee()]);
            }
            if (count($seen)!==count($expected)) fail(400,'Every active goal requires a rating.');
            query('UPDATE performance_reviews SET manager_comments=?,strengths=?,areas_for_improvement=?,development_plan=?,manager_submitted_at=NOW() WHERE review_id=?',[$comments,$strengths,$areas,$plan,$id]);
        } elseif ($action==='finalize') {
            reviewerAccess($r); if ($r['review_status']!=='Manager Review' || !$r['manager_submitted_at']) fail(409,'Manager evaluation is required before finalization.');
            $totals=one("SELECT COUNT(*) goals,COUNT(gr.rating) rated,SUM(g.weight) weight,SUM(g.weight*gr.rating)/100 rating FROM performance_goals g LEFT JOIN performance_goal_ratings gr ON gr.goal_id=g.goal_id AND gr.review_id=? WHERE g.employee_id=? AND g.cycle_id=? AND g.status<>'Cancelled'",[$id,(int)$r['employee_id'],(int)$r['cycle_id']]);
            if (!(int)$totals['goals'] || $totals['goals']!==$totals['rated'] || abs((float)$totals['weight']-100)>0.001) fail(409,'All active goals must be rated and weights must total 100%.');
            query("UPDATE performance_reviews SET overall_rating=?,review_status='Finalized',finalized_by=?,finalized_at=NOW() WHERE review_id=?",[round((float)$totals['rating'],2),(int)$_SESSION['user_id'],$id]); notifyEmployee((int)$r['employee_id'],'Performance review finalized','performance_reviews',$id);
        } else fail(404,'Unknown review action.');
        audit('performance.'.$action,'performance_reviews',$id); return record('performance_reviews','review_id',$id);
    }); reply($result,'Review operation completed.',$action==='create'?201:200);
}
if ($section==='feedback') {
    $id=id($b['review_id']??null); $r=record('performance_reviews','review_id',$id); reviewAccess($r);
    if ($action==='get') reply(rows('SELECT feedback_id,review_id,feedback_by,feedback_type,feedback,created_at FROM performance_feedback WHERE review_id=? ORDER BY feedback_id'.pageLimit(),[$id]));
    transaction(function() use($id,$b) {
        $r=record('performance_reviews','review_id',$id,true);
        if (in_array($r['review_status'],['Finalized','Closed'],true)) fail(409,'Feedback for finalized reviews is immutable.');
        $text=textValue($b['feedback']??null,'feedback',5000); $type=isAdminOrHR()?'HR':$_SESSION['role_name'];
        query('INSERT INTO performance_feedback(review_id,employee_id,feedback_by,feedback_type,feedback) VALUES (?,?,?,?,?)',[$id,(int)$r['employee_id'],(int)$_SESSION['user_id'],$type,$text]); audit('performance.feedback','performance_reviews',$id);
    }); reply(['review_id'=>$id],'Feedback recorded.',201);
}

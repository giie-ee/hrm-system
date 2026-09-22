<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
if ($action==='get') {
    if (isAdminOrHR()) reply(rows('SELECT * FROM training_courses ORDER BY course_id DESC'.pageLimit()));
    $params=[]; $where=scope('en.employee_id',$params);
    reply(rows("SELECT DISTINCT c.* FROM training_courses c JOIN training_enrollments en ON en.course_id=c.course_id WHERE $where ORDER BY c.course_id DESC".pageLimit(),$params));
}
if ($action==='enrollments') {
    $params=[]; $where=scope('en.employee_id',$params);
    if (isset($b['employee_id'])) { requireEmployeeAccess(id($b['employee_id'])); $where.=' AND en.employee_id=?'; $params[]=id($b['employee_id']); }
    reply(rows("SELECT en.*,c.title,c.provider,c.skills,c.start_date,c.end_date FROM training_enrollments en JOIN training_courses c ON c.course_id=en.course_id WHERE $where ORDER BY en.enrollment_id DESC".pageLimit(),$params));
}
requireRole(['Admin','HR']);
if ($action==='create' || $action==='update') {
    $id=$action==='update'?id($b['course_id']??null):null;
    $id=transaction(function() use($id,$b) {
        $old=$id?record('training_courses','course_id',$id,true):[];
        if ($old && in_array($old['status'],['Completed','Cancelled'],true)) fail(409,'Closed training courses are immutable.');
        $title=textValue($b['title']??$old['title']??null,'title',150); $desc=textValue($b['description']??$old['description']??null,'description',5000); $provider=textValue($b['provider']??$old['provider']??null,'provider',150); $skills=textValue($b['skills']??$old['skills']??'','skills',5000,false);
        [$start,$end]=dates(['start_date'=>$b['start_date']??$old['start_date']??null,'end_date'=>$b['end_date']??$old['end_date']??null]);
        $capacity=id($b['capacity']??$old['capacity']??null,'capacity'); $status=choice($b['status']??$old['status']??'Planned',['Planned','Open','In Progress','Completed','Cancelled']);
        $trans=['Planned'=>['Planned','Open','Cancelled'],'Open'=>['Open','In Progress','Cancelled'],'In Progress'=>['In Progress','Completed','Cancelled']];
        if (!$id && !in_array($status,['Planned','Open'],true)) fail(400,'A course starts Planned or Open.');
        if ($id && !in_array($status,$trans[$old['status']],true)) fail(409,'Invalid course transition.');
        if ($id) {
            $n=one("SELECT COUNT(*) n FROM training_enrollments WHERE course_id=? AND status<>'Cancelled'",[$id]); if ($capacity<(int)$n['n']) fail(409,'Capacity is below current enrollment.');
            if ($status==='Completed' && one("SELECT enrollment_id FROM training_enrollments WHERE course_id=? AND status NOT IN ('Completed','Cancelled')",[$id])) fail(409,'Complete or cancel all enrollments first.');
            if ($status==='Cancelled') query("UPDATE training_enrollments SET status='Cancelled' WHERE course_id=? AND status IN ('Enrolled','In Progress')",[$id]);
            query('UPDATE training_courses SET title=?,description=?,provider=?,skills=?,start_date=?,end_date=?,capacity=?,status=? WHERE course_id=?',[$title,$desc,$provider,$skills,$start,$end,$capacity,$status,$id]);
        } else { query('INSERT INTO training_courses(title,description,provider,skills,start_date,end_date,capacity,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)',[$title,$desc,$provider,$skills,$start,$end,$capacity,$status,(int)$_SESSION['user_id']]); $id=inserted(); }
        audit('training.course-saved','training_courses',$id); return $id;
    }); reply(record('training_courses','course_id',$id),'Training course saved.',$action==='create'?201:200);
}
$id=transaction(function() use($action,$b) {
    if ($action==='enroll') {
        $course=id($b['course_id']??null); $employee=id($b['employee_id']??null); $c=record('training_courses','course_id',$course,true); $e=record('employees','employee_id',$employee);
        if ($c['status']!=='Open' || $e['employment_status']!=='Active') fail(409,'Course must be Open and employee Active.');
        $n=one("SELECT COUNT(*) n FROM training_enrollments WHERE course_id=? AND status<>'Cancelled'",[$course]); if ((int)$n['n']>=(int)$c['capacity']) fail(409,'Training capacity has been reached.');
        query('INSERT INTO training_enrollments(course_id,employee_id) VALUES (?,?)',[$course,$employee]); $id=inserted(); notifyEmployee($employee,'Training assignment','training_enrollments',$id);
    } elseif ($action==='progress') {
        $id=id($b['enrollment_id']??null); $initial=record('training_enrollments','enrollment_id',$id); $c=record('training_courses','course_id',(int)$initial['course_id'],true); $en=record('training_enrollments','enrollment_id',$id,true);
        if (in_array($c['status'],['Completed','Cancelled'],true) || in_array($en['status'],['Completed','Cancelled'],true)) fail(409,'Closed training records cannot be changed.');
        $status=choice($b['status']??null,['In Progress','Completed','Cancelled']);
        if ($status!=='Cancelled' && $c['status']!=='In Progress') fail(409,'The course must be In Progress.');
        if ($status==='In Progress' && $en['status']!=='Enrolled') fail(409,'Enrollment already started.');
        if ($status==='Completed' && $en['status']!=='In Progress') fail(409,'Start enrollment before completion.');
        $date=$status==='Completed'?dateValue($b['completion_date']??null):null;
        if ($date && ($date<$c['start_date'] || $date>date('Y-m-d'))) fail(400,'Invalid completion date.');
        $score=isset($b['score'])?number($b['score'],0,100,'score'):null; $notes=textValue($b['development_notes']??'','development_notes',5000,false);
        query('UPDATE training_enrollments SET status=?,completion_date=?,score=?,development_notes=? WHERE enrollment_id=?',[$status,$date,$score,$notes,$id]);
    } else fail(404,'Unknown training action.');
    audit('training.'.$action,'training_enrollments',$id); return $id;
}); reply(record('training_enrollments','enrollment_id',$id),'Training enrollment saved.',$action==='enroll'?201:200);

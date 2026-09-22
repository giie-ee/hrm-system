<?php
$b=HRMS_METHOD==='POST'?input():$_GET;
if ($action==='get') { requireLogin(); reply(rows('SELECT * FROM benefits ORDER BY benefit_id'.pageLimit())); }
if ($action==='assignments' || $action==='history') {
    $params=[]; $where=scope('eb.employee_id',$params);
    if (isset($b['employee_id'])) { requireEmployeeAccess(id($b['employee_id'])); $where.=' AND eb.employee_id=?'; $params[]=id($b['employee_id']); }
    if ($action==='history') reply(rows("SELECT h.*,eb.employee_id,b.benefit_name FROM benefit_history h JOIN employee_benefits eb ON eb.employee_benefit_id=h.employee_benefit_id JOIN benefits b ON b.benefit_id=eb.benefit_id WHERE $where ORDER BY h.history_id DESC".pageLimit(),$params));
    reply(rows("SELECT eb.*,b.benefit_name,b.benefit_type FROM employee_benefits eb JOIN benefits b ON b.benefit_id=eb.benefit_id WHERE $where ORDER BY eb.employee_benefit_id".pageLimit(),$params));
}
requireRole(['Admin','HR']);
if ($action==='create' || $action==='update') {
    $id=$action==='update'?id($b['benefit_id']??null):null; $old=$id?record('benefits','benefit_id',$id):[];
    $name=textValue($b['benefit_name']??$old['benefit_name']??null,'benefit_name',100); $desc=textValue($b['description']??$old['description']??'','description',255,false);
    $type=choice($b['benefit_type']??$old['benefit_type']??null,['Health','Insurance','Retirement','Allowance','Other'],'benefit_type'); $provider=textValue($b['provider']??$old['provider']??'','provider',150,false);
    $amount=money($b['default_amount']??$old['default_amount']??0); $status=choice($b['status']??$old['status']??'Active',['Active','Inactive']);
    $id=transaction(function() use($id,$name,$desc,$type,$provider,$amount,$status) {
        if ($id) query('UPDATE benefits SET benefit_name=?,description=?,benefit_type=?,provider=?,default_amount=?,status=? WHERE benefit_id=?',[$name,$desc,$type,$provider,$amount,$status,$id]);
        else { query('INSERT INTO benefits(benefit_name,description,benefit_type,provider,default_amount,status) VALUES (?,?,?,?,?,?)',[$name,$desc,$type,$provider,$amount,$status]); $id=inserted(); }
        audit('benefit.saved','benefits',$id); return $id;
    }); reply(record('benefits','benefit_id',$id),'Benefit saved.',$action==='create'?201:200);
}
$id=transaction(function() use($b,$action) {
    if ($action==='assign') {
        $employee=id($b['employee_id']??null); $benefit=id($b['benefit_id']??null); record('employees','employee_id',$employee,true); $def=record('benefits','benefit_id',$benefit,true);
        if ($def['status']!=='Active') fail(409,'Benefit is inactive.');
        if (one('SELECT employee_benefit_id FROM employee_benefits WHERE employee_id=? AND benefit_id=?',[$employee,$benefit])) fail(409,'An assignment exists. Update it to preserve its history.');
        $from=dateValue($b['enrollment_date']??null); $to=isset($b['end_date'])?dateValue($b['end_date']):null; $amount=money($b['amount']??$def['default_amount']);
        if ($to && $to<$from) fail(400,'Invalid benefit dates.');
        query('INSERT INTO employee_benefits(employee_id,benefit_id,enrollment_date,end_date,amount) VALUES (?,?,?,?,?)',[$employee,$benefit,$from,$to,$amount]); $id=inserted();
    } elseif ($action==='update-assignment') {
        $id=id($b['employee_benefit_id']??null); $old=record('employee_benefits','employee_benefit_id',$id,true); $status=choice($b['status']??$old['status'],['Active','Inactive','Pending']);
        $from=dateValue($b['enrollment_date']??$old['enrollment_date']); $to=array_key_exists('end_date',$b)?($b['end_date']===null?null:dateValue($b['end_date'])):$old['end_date'];
        if ($to && $to<$from) fail(400,'Invalid benefit dates.');
        $def=record('benefits','benefit_id',(int)$old['benefit_id']); if ($status==='Active' && $def['status']!=='Active') fail(409,'Benefit is inactive.');
        query('UPDATE employee_benefits SET status=?,enrollment_date=?,end_date=?,amount=? WHERE employee_benefit_id=?',[$status,$from,$to,money($b['amount']??$old['amount']),$id]);
    } else fail(404,'Unknown benefit action.');
    query('INSERT INTO benefit_history(employee_benefit_id,status,amount,enrollment_date,end_date,changed_by) SELECT employee_benefit_id,status,amount,enrollment_date,end_date,? FROM employee_benefits WHERE employee_benefit_id=?',[(int)$_SESSION['user_id'],$id]); audit('benefit.'.$action,'employee_benefits',$id); return $id;
}); reply(record('employee_benefits','employee_benefit_id',$id),'Benefit assignment saved.',$action==='assign'?201:200);

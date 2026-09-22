<?php
requireRole(['Admin','HR']); $b=HRMS_METHOD==='POST'?input():$_GET;
if ($action==='get') {
    $where=$_SESSION['role_name']==='HR'?"WHERE r.role_name<>'Admin'":'';
    reply(rows("SELECT u.user_id,u.employee_id,u.username,u.email,u.account_status,u.last_login,r.role_id,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id $where ORDER BY u.user_id".pageLimit()));
}
function newPassword($p): string {
    $p=textValue($p,'password',128);
    if (strlen($p)<10 || !preg_match('/[A-Z]/',$p) || !preg_match('/[a-z]/',$p) || !preg_match('/[0-9]/',$p) || !preg_match('/[^a-zA-Z0-9]/',$p)) fail(400,'Password requires at least 10 characters, upper/lowercase, number and symbol.');
    return password_hash($p,PASSWORD_DEFAULT);
}
if ($action==='create') {
    $employee=id($b['employee_id']??null); $role=id($b['role_id']??null); $r=record('roles','role_id',$role);
    if ($_SESSION['role_name']==='HR' && $r['role_name']==='Admin') fail(403,'Only Admin can assign the Admin role.');
    $username=textValue($b['username']??null,'username',50); if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D',$username)) fail(400,'Invalid username.');
    $hash=newPassword($b['password']??null);
    $id=transaction(function() use($employee,$role,$username,$hash) {
        $e=record('employees','employee_id',$employee,true);
        if ($e['employment_status']!=='Active') fail(409,'Employee must be active.');
        if (one('SELECT user_id FROM users WHERE employee_id=?',[$employee])) fail(409,'Employee already has an account.');
        query('INSERT INTO users(employee_id,role_id,username,email,password_hash) VALUES (?,?,?,?,?)',[$employee,$role,$username,$e['email'],$hash]); $id=inserted(); audit('user.created','users',$id,['role_id'=>$role]); return $id;
    }); reply(['user_id'=>$id],'Account created.',201);
}
$id=id($b['user_id']??null);
transaction(function() use($action,$id,$b) {
    // Lock all administrator accounts to serialize last-admin checks.
    rows("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='Admin' FOR UPDATE");
    $u=record('users','user_id',$id,true); $old=record('roles','role_id',(int)$u['role_id']);
    if ($_SESSION['role_name']==='HR' && $old['role_name']==='Admin') fail(403,'Only Admin can manage administrator accounts.');
    if ($action==='reset-password') { $hash=newPassword($b['password']??null); query('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE user_id=?',[$hash,$id]); }
    elseif ($action==='update') {
        $role=id($b['role_id']??$u['role_id']); $r=record('roles','role_id',$role); $status=choice($b['account_status']??$u['account_status'],['Active','Inactive','Locked']);
        if ($_SESSION['role_name']==='HR' && $r['role_name']==='Admin') fail(403,'Only Admin can assign Admin.');
        if ($id===(int)$_SESSION['user_id'] && ($role!==(int)$u['role_id'] || $status!=='Active')) fail(409,'You cannot demote or deactivate yourself.');
        if ($old['role_name']==='Admin' && ($r['role_name']!=='Admin' || $status!=='Active')) {
            $n=one("SELECT COUNT(*) n FROM users u JOIN roles r ON r.role_id=u.role_id JOIN employees e ON e.employee_id=u.employee_id WHERE r.role_name='Admin' AND u.account_status='Active' AND e.employment_status='Active' AND u.user_id<>?",[$id]); if ((int)$n['n']===0) fail(409,'An active administrator must remain.');
        }
        query('UPDATE users SET role_id=?,account_status=?,auth_version=auth_version+1 WHERE user_id=?',[$role,$status,$id]);
    } else fail(404,'Unknown account action.');
    audit('user.'.$action,'users',$id);
}); reply(['user_id'=>$id]);

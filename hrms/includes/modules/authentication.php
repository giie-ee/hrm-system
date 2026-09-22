<?php
if ($action === 'login') {
    $b=input(); $username=textValue($b['username'] ?? null,'username',50); $password=textValue($b['password'] ?? null,'password',128);
    // Per-IP throttling survives clearing cookies and changing usernames.
    $key=hash('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $result=transaction(function() use ($key,$username,$password) {
        query('INSERT INTO login_attempts(attempt_key,attempts,window_started) VALUES (?,0,NOW()) ON CONFLICT (attempt_key) DO NOTHING',[$key]);
        $a=one('SELECT * FROM login_attempts WHERE attempt_key=? FOR UPDATE',[$key]);
        if (strtotime($a['window_started']) < time()-900) { query('UPDATE login_attempts SET attempts=0,window_started=NOW() WHERE attempt_key=?',[$key]); $a['attempts']=0; }
        if ((int)$a['attempts']>=15) return ['limited'=>true];
        $u=one('SELECT u.*,r.role_name,e.employment_status FROM users u JOIN roles r ON r.role_id=u.role_id JOIN employees e ON e.employee_id=u.employee_id WHERE username=?',[$username]);
        $valid=password_verify($password,$u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$u || !$valid || $u['account_status']!=='Active' || $u['employment_status']!=='Active') {
            query('UPDATE login_attempts SET attempts=attempts+1 WHERE attempt_key=?',[$key]); audit('login.failed','authentication'); return null;
        }
        query('UPDATE login_attempts SET attempts=0 WHERE attempt_key=?',[$key]);
        query('UPDATE users SET last_login=NOW() WHERE user_id=?',[(int)$u['user_id']]); return $u;
    });
    if (isset($result['limited'])) { header('Retry-After: 900'); fail(429,'Too many login attempts. Try again later.'); }
    if (!$result) fail(401,'Invalid username or password.');
    session_regenerate_id(true); $_SESSION=[];
    foreach (['user_id','employee_id','role_id','role_name','username','auth_version'] as $key) $_SESSION[$key]=$result[$key];
    $_SESSION['csrf_token']=bin2hex(random_bytes(32)); $_SESSION['last_activity']=time();
    audit('login','users',(int)$result['user_id']);
    $u=array_intersect_key($result,array_flip(['user_id','employee_id','role_id','role_name','username','email']));
    reply(['user'=>$u,'csrf_token'=>$_SESSION['csrf_token']],'Login successful.',200,['user'=>$u,'csrf_token'=>$_SESSION['csrf_token']]);
}
if ($action==='me') reply(['user'=>array_intersect_key($_SESSION,array_flip(['user_id','employee_id','role_id','role_name','username'])),'csrf_token'=>$_SESSION['csrf_token']]);
if ($action==='logout') { audit('logout','users',(int)$_SESSION['user_id']); $_SESSION=[]; session_destroy(); setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax']); reply([], 'Logged out.'); }

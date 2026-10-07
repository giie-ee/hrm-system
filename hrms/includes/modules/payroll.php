<?php
$b=HRMS_METHOD==='POST' ? input() : $_GET;
if ($action==='get') {
    requireLogin();
    $params=[];
    $where=scope('p.employee_id',$params);
    $records=rows(
        'SELECT p.*,CONCAT(e.first_name,\' \',e.last_name) employee_name,e.employee_number,'
        . 'pa.working_days,pa.days_present,pa.days_absent,pa.days_on_leave,pa.days_late,'
        . 'pa.expected_hours,pa.hours_worked,pa.overtime_hours,pa.shortfall_hours '
        . 'FROM payroll p JOIN employees e ON e.employee_id=p.employee_id '
        . 'LEFT JOIN payroll_attendance pa ON pa.payroll_id=p.payroll_id '
        . "WHERE {$where} ORDER BY p.payroll_id DESC".pageLimit(),
        $params
    );
    reply($records,'Payroll records retrieved.');
}
if ($action==='get-items') {
    requireLogin();
    $id=id($b['payroll_id'] ?? null,'payroll_id');
    $payroll=record('payroll','payroll_id',$id);
    requireEmployeeAccess((int)$payroll['employee_id']);
    $items=rows('SELECT * FROM payroll_items WHERE payroll_id=? ORDER BY payroll_item_id',[$id]);
    reply($items,'Payroll items retrieved successfully.',200,['payroll_id'=>$id,'items'=>$items,'total_items'=>count($items)]);
}
requireRole(['Admin','HR']);
if ($action==='position-bands') {
    $guidelines=rows(
        "SELECT g.salary_guideline_id,g.position_id,p.position_name,d.department_name,"
        . "g.recommended_basic_salary,g.currency,g.effective_from,g.effective_to,g.status,g.notes,"
        . "COUNT(e.employee_id) employee_count "
        . "FROM position_salary_guidelines g "
        . "JOIN positions p ON p.position_id=g.position_id "
        . "JOIN departments d ON d.department_id=p.department_id "
        . "LEFT JOIN employees e ON e.position_id=p.position_id AND e.employment_status='Active' "
        . "WHERE g.status='Active' AND g.effective_from<=CURRENT_DATE "
        . "AND (g.effective_to IS NULL OR g.effective_to>=CURRENT_DATE) "
        . "GROUP BY g.salary_guideline_id,p.position_name,d.department_name "
        . "ORDER BY d.department_name,p.position_name"
    );
    reply($guidelines,'Position salary guidelines retrieved.');
}
if ($action==='salary-overview') {
    $overview=rows(
        "SELECT e.employee_id,e.employee_number,CONCAT(e.first_name,' ',e.last_name) employee_name,"
        . "d.department_name,p.position_name,s.basic_salary,s.currency,s.effective_from,"
        . "g.recommended_basic_salary,g.currency guideline_currency "
        . "FROM employees e "
        . "JOIN departments d ON d.department_id=e.department_id "
        . "JOIN positions p ON p.position_id=e.position_id "
        . "LEFT JOIN LATERAL ("
        . "SELECT es.basic_salary,es.currency,es.effective_from FROM employee_salaries es "
        . "WHERE es.employee_id=e.employee_id AND es.salary_status='Active' "
        . "AND es.effective_from<=CURRENT_DATE AND (es.effective_to IS NULL OR es.effective_to>=CURRENT_DATE) "
        . "ORDER BY es.effective_from DESC LIMIT 1) s ON TRUE "
        . "LEFT JOIN LATERAL ("
        . "SELECT pg.recommended_basic_salary,pg.currency FROM position_salary_guidelines pg "
        . "WHERE pg.position_id=e.position_id AND pg.status='Active' "
        . "AND pg.effective_from<=CURRENT_DATE AND (pg.effective_to IS NULL OR pg.effective_to>=CURRENT_DATE) "
        . "ORDER BY pg.effective_from DESC LIMIT 1) g ON TRUE "
        . "WHERE e.employment_status='Active' ORDER BY d.department_name,p.position_name,e.last_name,e.first_name"
    );
    reply($overview,'Employee salary overview retrieved.');
}
if ($action==='components') {
    reply(rows("SELECT * FROM payroll_components WHERE status='Active' ORDER BY component_type,component_name"),'Payroll components retrieved.');
}
if ($action==='assign-component') {
    $employee=id($b['employee_id']??null,'employee_id');
    $component=id($b['payroll_component_id']??null,'payroll_component_id');
    $value=money($b['component_value']??null,'component_value');
    $from=dateValue($b['effective_from']??null,'effective_from');
    $to=isset($b['effective_to']) && $b['effective_to']!=='' ? dateValue($b['effective_to'],'effective_to') : null;
    if ($to!==null && $to<$from) fail(400,'Invalid component date range.');
    $assignment=transaction(function() use($employee,$component,$value,$from,$to) {
        record('employees','employee_id',$employee,true);
        record('payroll_components','payroll_component_id',$component);
        if (one('SELECT employee_payroll_component_id FROM employee_payroll_components WHERE employee_id=? AND payroll_component_id=? AND status=\'Active\' AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?)',[$employee,$component,$to??'2100-12-31',$from])) fail(409,'This recurring payroll component overlaps an active assignment.');
        query('INSERT INTO employee_payroll_components(employee_id,payroll_component_id,component_value,effective_from,effective_to,created_by) VALUES (?,?,?,?,?,?)',[$employee,$component,$value,$from,$to,(int)$_SESSION['user_id']]);
        $id=inserted();
        audit('payroll.component-assigned','employees',$employee,['employee_payroll_component_id'=>$id,'payroll_component_id'=>$component]);
        return $id;
    });
    reply(['employee_payroll_component_id'=>$assignment],'Recurring payroll component assigned.',201);
}
if ($action==='create') {
    $employee=id($b['employee_id'] ?? null,'employee_id'); [$start,$end]=dates($b,'pay_period_start','pay_period_end');
    $p=transaction(function() use($employee,$start,$end) {
        record('employees','employee_id',$employee,true);
        if (one('SELECT payroll_id FROM payroll WHERE employee_id=? AND pay_period_start<=? AND pay_period_end>=?',[$employee,$end,$start])) fail(409,'Payroll periods cannot overlap.');
        $salary=one("SELECT * FROM employee_salaries WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) ORDER BY effective_from DESC LIMIT 1",[$employee,$start,$end]);
        if (!$salary) fail(409,'One salary record must cover the entire payroll period.');
        $basic=money($salary['basic_salary'],'basic_salary');
        query("INSERT INTO payroll(employee_id,pay_period_start,pay_period_end,basic_salary,gross_salary,net_salary,currency,created_by) VALUES (?,?,?,?,?,?,?,?)",[$employee,$start,$end,$basic,$basic,$basic,$salary['currency']??'ZMW',(int)$_SESSION['user_id']]);
        $id=inserted();
        query(
            "INSERT INTO payroll_items(payroll_id,item_type,item_name,amount,description) "
            . "SELECT ?,pc.component_type,pc.component_name,"
            . "CASE WHEN pc.calculation_type='Percentage' THEN ROUND(? * epc.component_value / 100,2) ELSE epc.component_value END,"
            . "pc.description FROM employee_payroll_components epc "
            . "JOIN payroll_components pc ON pc.payroll_component_id=epc.payroll_component_id "
            . "WHERE epc.employee_id=? AND epc.status='Active' AND pc.status='Active' "
            . "AND epc.effective_from<=? AND (epc.effective_to IS NULL OR epc.effective_to>=?)",
            [$id,$basic,$employee,$end,$start]
        );
        audit('payroll.created','payroll',$id,['employee_id'=>$employee,'pay_period_start'=>$start,'pay_period_end'=>$end]);
        return record('payroll','payroll_id',$id);
    }); reply($p,'Payroll created successfully.',200,$p);
}
$id=id($b['payroll_id'] ?? null,'payroll_id');
$p=transaction(function() use($action,$id,$b) {
    $p=record('payroll','payroll_id',$id,true);
    if ($p['payroll_status']!=='Draft') fail(409,'Only Draft payroll can be changed or processed.');
    if ($action==='add-item') {
        $type=choice($b['item_type']??null,['Allowance','Deduction'],'item_type'); $name=textValue($b['item_name']??null,'item_name',100);
        $amount=money($b['amount']??null); $description=textValue($b['description']??'','description',255,false);
        if (one('SELECT payroll_item_id FROM payroll_items WHERE payroll_id=? AND item_type=? AND item_name=?',[$id,$type,$name])) fail(409,'This payroll item already exists.');
        query('INSERT INTO payroll_items(payroll_id,item_type,item_name,amount,description) VALUES (?,?,?,?,?)',[$id,$type,$name,$amount,$description]);
        $item=inserted(); audit('payroll.item-added','payroll',$id); return ['payroll_item_id'=>$item,'payroll_id'=>$id,'item_type'=>$type,'item_name'=>$name,'amount'=>$amount,'description'=>$description];
    }
    // SQL DECIMAL arithmetic prevents binary floating-point accumulation for money.
    $totals=one("SELECT COALESCE(SUM(CASE WHEN item_type='Allowance' THEN amount ELSE 0 END),0) allowances,COALESCE(SUM(CASE WHEN item_type='Deduction' THEN amount ELSE 0 END),0) deductions FROM payroll_items WHERE payroll_id=?",[$id]);
    query('UPDATE payroll SET total_allowances=?,total_deductions=?,gross_salary=basic_salary+?,net_salary=basic_salary+?-? WHERE payroll_id=?',[$totals['allowances'],$totals['deductions'],$totals['allowances'],$totals['allowances'],$totals['deductions'],$id]);
    $p=record('payroll','payroll_id',$id);
    if ((float)$p['net_salary']<0) fail(409,'Deductions cannot exceed gross salary.');
    if ($action==='process') {
        query("UPDATE payroll SET payroll_status='Processed',payment_date=CURRENT_DATE,processed_by=?,processed_at=CURRENT_TIMESTAMP WHERE payroll_id=?",[(int)$_SESSION['user_id'],$id]);
        $a=one(
            "WITH target AS ("
            . "SELECT COALESCE(e.work_policy_id,(SELECT work_policy_id FROM work_policies WHERE status='Active' LIMIT 1)) work_policy_id "
            . "FROM employees e WHERE e.employee_id=?),"
            . "schedule AS ("
            . "SELECT day_value::DATE work_date,wpd.expected_hours "
            . "FROM target t CROSS JOIN generate_series(CAST(? AS DATE),CAST(? AS DATE),INTERVAL '1 day') day_value "
            . "JOIN work_policy_days wpd ON wpd.work_policy_id=t.work_policy_id "
            . "AND wpd.day_of_week=EXTRACT(DOW FROM day_value)::INTEGER WHERE wpd.is_working_day=TRUE),"
            . "scheduled_results AS ("
            . "SELECT s.work_date,s.expected_hours,a.status,a.hours_worked,a.late_minutes,"
            . "EXISTS(SELECT 1 FROM leave_requests lr WHERE lr.employee_id=? AND lr.status='Approved' "
            . "AND s.work_date BETWEEN lr.start_date AND lr.end_date) approved_leave "
            . "FROM schedule s LEFT JOIN attendance a ON a.employee_id=? AND a.attendance_date=s.work_date),"
            . "actual AS ("
            . "SELECT COALESCE(SUM(hours_worked),0) hours_worked,"
            . "COALESCE(SUM(CASE WHEN status IN ('Present','Late','Present (Half Day)','Late (Half Day)','Half-Day','Half Day','Early Checkout') THEN 1 ELSE 0 END),0) present,"
            . "COALESCE(SUM(CASE WHEN late_minutes>0 OR status IN ('Late','Late (Half Day)') THEN 1 ELSE 0 END),0) late "
            . "FROM attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND ?) "
            . "SELECT COUNT(*) working_days,COALESCE((SELECT present FROM actual),0) present,"
            . "COALESCE(SUM(CASE WHEN status='Absent' OR (status IS NULL AND approved_leave=FALSE) THEN 1 ELSE 0 END),0) absent,"
            . "COALESCE(SUM(CASE WHEN status='On Leave' OR (status IS NULL AND approved_leave=TRUE) THEN 1 ELSE 0 END),0) on_leave,"
            . "COALESCE((SELECT late FROM actual),0) late,COALESCE(SUM(expected_hours),0) expected_hours,"
            . "COALESCE((SELECT hours_worked FROM actual),0) hours_worked FROM scheduled_results",
            [
                (int)$p['employee_id'],$p['pay_period_start'],$p['pay_period_end'],
                (int)$p['employee_id'],(int)$p['employee_id'],(int)$p['employee_id'],
                $p['pay_period_start'],$p['pay_period_end']
            ]
        );
        $expected=(float)$a['expected_hours']; $worked=(float)$a['hours_worked'];
        $overtime=max(0,$worked-$expected); $shortfall=max(0,$expected-$worked);
        query('INSERT INTO payroll_attendance(payroll_id,employee_id,working_days,days_present,days_absent,days_on_leave,days_late,expected_hours,hours_worked,overtime_hours,shortfall_hours,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT (payroll_id,employee_id) DO UPDATE SET working_days=EXCLUDED.working_days,days_present=EXCLUDED.days_present,days_absent=EXCLUDED.days_absent,days_on_leave=EXCLUDED.days_on_leave,days_late=EXCLUDED.days_late,expected_hours=EXCLUDED.expected_hours,hours_worked=EXCLUDED.hours_worked,overtime_hours=EXCLUDED.overtime_hours,shortfall_hours=EXCLUDED.shortfall_hours,notes=EXCLUDED.notes',[$id,(int)$p['employee_id'],$a['working_days'],$a['present'],$a['absent'],$a['on_leave'],$a['late'],$expected,$worked,$overtime,$shortfall,'Attendance snapshot captured when payroll was processed; salary is not automatically prorated.']);
        notifyEmployee((int)$p['employee_id'],'Payroll processed','payroll',$id);
    }
    audit('payroll.'.$action,'payroll',$id,['employee_id'=>(int)$p['employee_id'],'performed_by'=>(int)$_SESSION['user_id']]);
    $p=record('payroll','payroll_id',$id);
    $attendance=one('SELECT working_days,days_present,days_absent,days_on_leave,days_late,expected_hours,hours_worked,overtime_hours,shortfall_hours FROM payroll_attendance WHERE payroll_id=?',[$id]);
    if ($attendance) $p=array_merge($p,$attendance);
    $p['payroll_items']=rows('SELECT item_type,item_name,amount,description FROM payroll_items WHERE payroll_id=?',[$id]);
    return $p;
});
reply($p,'Payroll operation completed successfully.',200,$p);

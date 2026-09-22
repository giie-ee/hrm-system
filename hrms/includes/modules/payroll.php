<?php
requireRole(['Admin','HR']);
$b=HRMS_METHOD==='POST' ? input() : $_GET;
if ($action==='get') reply(rows('SELECT * FROM payroll ORDER BY payroll_id DESC'.pageLimit()));
if ($action==='create') {
    $employee=id($b['employee_id'] ?? null,'employee_id'); [$start,$end]=dates($b,'pay_period_start','pay_period_end');
    $p=transaction(function() use($employee,$start,$end) {
        record('employees','employee_id',$employee,true);
        if (one('SELECT payroll_id FROM payroll WHERE employee_id=? AND pay_period_start<=? AND pay_period_end>=?',[$employee,$end,$start])) fail(409,'Payroll periods cannot overlap.');
        $salary=one("SELECT * FROM employee_salaries WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) ORDER BY effective_from DESC LIMIT 1",[$employee,$start,$end]);
        if (!$salary) fail(409,'One salary record must cover the entire payroll period.');
        $basic=money($salary['basic_salary'],'basic_salary');
        query("INSERT INTO payroll(employee_id,pay_period_start,pay_period_end,basic_salary,gross_salary,net_salary) VALUES (?,?,?,?,?,?)",[$employee,$start,$end,$basic,$basic,$basic]);
        $id=inserted(); audit('payroll.created','payroll',$id); return record('payroll','payroll_id',$id);
    }); reply($p,'Payroll created successfully.',200,$p);
}
$id=id($b['payroll_id'] ?? null,'payroll_id');
if ($action==='get-items') {
    record('payroll','payroll_id',$id); $items=rows('SELECT * FROM payroll_items WHERE payroll_id=? ORDER BY payroll_item_id',[$id]);
    reply($items,'Payroll items retrieved successfully.',200,['payroll_id'=>$id,'items'=>$items,'total_items'=>count($items)]);
}
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
        query("UPDATE payroll SET payroll_status='Processed',payment_date=CURRENT_DATE WHERE payroll_id=?",[$id]);
        $a=one("SELECT COUNT(*) working_days,COALESCE(SUM(CASE WHEN status IN ('Present','Late','Half-Day') THEN 1 ELSE 0 END),0) present,COALESCE(SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END),0) absent,COALESCE(SUM(CASE WHEN status='On Leave' THEN 1 ELSE 0 END),0) on_leave,COALESCE(SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END),0) late FROM attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND ?",[(int)$p['employee_id'],$p['pay_period_start'],$p['pay_period_end']]);
        query('INSERT INTO payroll_attendance(payroll_id,employee_id,working_days,days_present,days_absent,days_on_leave,days_late,notes) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT (payroll_id,employee_id) DO UPDATE SET working_days=EXCLUDED.working_days,days_present=EXCLUDED.days_present,days_absent=EXCLUDED.days_absent,days_on_leave=EXCLUDED.days_on_leave,days_late=EXCLUDED.days_late,notes=EXCLUDED.notes',[$id,(int)$p['employee_id'],$a['working_days'],$a['present'],$a['absent'],$a['on_leave'],$a['late'],'Recorded attendance days only; no schedule or automatic salary proration.']);
        notifyEmployee((int)$p['employee_id'],'Payroll processed','payroll',$id);
    }
    audit('payroll.'.$action,'payroll',$id);
    $p=record('payroll','payroll_id',$id); $p['payroll_items']=rows('SELECT item_type,item_name,amount,description FROM payroll_items WHERE payroll_id=?',[$id]); return $p;
});
reply($p,'Payroll operation completed successfully.',200,$p);

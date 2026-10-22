<?php

declare(strict_types=1);

/**
 * Marks employees with approved leave as 'On Leave' once their configured
 * working day has ended. There is no scheduler on the host, so this runs
 * whenever attendance is read or a check-in happens; it is idempotent and also
 * back-fills the previous 30 days. Each employee's policy timezone and daily
 * end time are respected, including half days and organisation-specific hours.
 */
function attendanceSyncApprovedLeave(bool $throw = false): int
{
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        // Include a one-day timezone margin; the SQL predicate below decides
        // whether each policy-local workday has actually ended.
        $first = $now->modify('-31 days');
        $last = $now->modify('+1 day');
        $statement = query(
            "INSERT INTO attendance(employee_id,attendance_date,hours_worked,status,notes,scheduled_start,scheduled_end,"
            . "expected_hours,attendance_source,verification_status) "
            . "SELECT lr.employee_id,d.day::date,0,'On Leave','Automatically marked from approved leave.',"
            . "wpd.start_time,wpd.end_time,wpd.expected_hours,'System','Manager Confirmed' "
            . "FROM generate_series(CAST(? AS DATE),CAST(? AS DATE),INTERVAL '1 day') AS d(day) "
            . "JOIN leave_requests lr ON lr.status='Approved' AND d.day::date BETWEEN lr.start_date AND lr.end_date "
            . "JOIN employees e ON e.employee_id=lr.employee_id AND e.employment_status='Active' "
            . "JOIN work_policies wp ON wp.work_policy_id=COALESCE(e.work_policy_id,"
            . "(SELECT work_policy_id FROM work_policies WHERE status='Active' ORDER BY work_policy_id LIMIT 1)) "
            . "JOIN work_policy_days wpd ON wpd.work_policy_id=wp.work_policy_id "
            . "AND wpd.day_of_week=EXTRACT(DOW FROM d.day)::INTEGER AND wpd.is_working_day "
            . "WHERE d.day::date < (CURRENT_TIMESTAMP AT TIME ZONE wp.timezone)::date "
            . "OR (d.day::date = (CURRENT_TIMESTAMP AT TIME ZONE wp.timezone)::date "
            . "AND wpd.end_time <= (CURRENT_TIMESTAMP AT TIME ZONE wp.timezone)::time) "
            . "ON CONFLICT (employee_id,attendance_date) DO NOTHING",
            [$first->format('Y-m-d'), $last->format('Y-m-d')]
        );
        return (int) $statement->affected_rows;
    } catch (Throwable $exception) {
        error_log('Leave attendance sync failed: ' . $exception->getMessage());
        if ($throw) throw $exception;
        return 0;
    }
}

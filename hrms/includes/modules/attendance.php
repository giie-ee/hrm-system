<?php

$body = HRMS_METHOD === 'POST' ? input() : $_GET;

function attendanceStatusValues(): array
{
    return ['Present', 'Absent', 'Late', 'Half-Day', 'Early Checkout', 'On Leave', 'Off Schedule'];
}

function attendanceTimeMinutes(?string $time): ?int
{
    if ($time === null || $time === '') return null;
    $parsed = DateTimeImmutable::createFromFormat('!H:i:s', strlen($time) === 5 ? $time . ':00' : $time);
    if (!$parsed) fail(500, 'Stored working-hour policy is invalid.');
    return ((int) $parsed->format('H') * 60) + (int) $parsed->format('i');
}

function attendanceInputTime(mixed $value, string $name): ?string
{
    if ($value === null || $value === '') return null;
    if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/D', $value)) {
        fail(400, "{$name} must use HH:MM or HH:MM:SS.");
    }
    return strlen($value) === 5 ? $value . ':00' : $value;
}

function attendanceTimezone(int $employeeId): DateTimeZone
{
    $row = one(
        'SELECT wp.timezone FROM employees e JOIN work_policies wp '
        . 'ON wp.work_policy_id=COALESCE(e.work_policy_id,'
        . '(SELECT work_policy_id FROM work_policies WHERE status=\'Active\' ORDER BY work_policy_id LIMIT 1)) '
        . 'WHERE e.employee_id=?',
        [$employeeId]
    ) ?? fail(409, 'No working-hour policy is assigned to this employee.');
    try { return new DateTimeZone((string) $row['timezone']); }
    catch (Throwable) { fail(500, 'Stored working-hour timezone is invalid.'); }
}

function attendancePolicy(int $employeeId, string $date): array
{
    $dayOfWeek = (int) (new DateTimeImmutable($date))->format('w');
    $policy = one(
        'SELECT wp.work_policy_id,wp.policy_name,wp.timezone,wp.grace_minutes,'
        . 'wp.early_departure_grace_minutes,wp.minimum_half_day_hours,'
        . 'wpd.is_working_day,wpd.start_time,wpd.end_time,wpd.expected_hours '
        . 'FROM employees e '
        . 'JOIN work_policies wp ON wp.work_policy_id=COALESCE(e.work_policy_id,'
        . '(SELECT work_policy_id FROM work_policies WHERE status=\'Active\' ORDER BY work_policy_id LIMIT 1)) '
        . 'JOIN work_policy_days wpd ON wpd.work_policy_id=wp.work_policy_id AND wpd.day_of_week=? '
        . 'WHERE e.employee_id=?',
        [$dayOfWeek, $employeeId]
    );

    if (!$policy) fail(409, 'No working-hour policy is assigned to this employee.');
    $policy['is_working_day'] = filter_var($policy['is_working_day'], FILTER_VALIDATE_BOOLEAN);
    return $policy;
}

function attendanceRecordResponse(array $record): array
{
    foreach (['attendance_id', 'employee_id', 'late_minutes', 'early_departure_minutes'] as $field) {
        if (array_key_exists($field, $record)) $record[$field] = (int) $record[$field];
    }
    foreach (['hours_worked', 'expected_hours'] as $field) {
        if (array_key_exists($field, $record)) $record[$field] = (float) $record[$field];
    }
    return $record;
}

if ($action === 'policy') {
    requireLogin();
    $employeeId = isset($body['employee_id']) ? id($body['employee_id'], 'employee_id') : ownEmployee();
    requireEmployeeAccess($employeeId);
    $date = isset($body['date']) ? dateValue($body['date'], 'date') : date('Y-m-d');
    $policy = attendancePolicy($employeeId, $date);
    $policy['work_policy_id'] = (int) $policy['work_policy_id'];
    $policy['grace_minutes'] = (int) $policy['grace_minutes'];
    $policy['early_departure_grace_minutes'] = (int) $policy['early_departure_grace_minutes'];
    $policy['minimum_half_day_hours'] = (float) $policy['minimum_half_day_hours'];
    $policy['expected_hours'] = (float) $policy['expected_hours'];
    reply($policy, 'Working-hour policy retrieved.');
}

if ($action === 'get') {
    requireLogin();
    $params = [];
    $where = scope('a.employee_id', $params);

    if (isset($body['employee_id']) && $body['employee_id'] !== '') {
        $employeeId = id($body['employee_id'], 'employee_id');
        requireEmployeeAccess($employeeId);
        $where .= ' AND a.employee_id=?';
        $params[] = $employeeId;
    }
    if (isset($body['date']) && $body['date'] !== '') {
        $where .= ' AND a.attendance_date=?';
        $params[] = dateValue($body['date'], 'date');
    }
    if (isset($body['status']) && $body['status'] !== '') {
        $where .= ' AND a.status=?';
        $params[] = choice($body['status'], attendanceStatusValues(), 'status');
    }

    $records = rows(
        'SELECT a.*,CONCAT(e.first_name,\' \',e.last_name) employee_name,'
        . 'e.employee_number,d.department_name '
        . 'FROM attendance a '
        . 'JOIN employees e ON e.employee_id=a.employee_id '
        . 'LEFT JOIN departments d ON d.department_id=e.department_id '
        . "WHERE {$where} ORDER BY a.attendance_date DESC,a.attendance_id DESC" . pageLimit(),
        $params
    );
    reply(array_map('attendanceRecordResponse', $records), 'Attendance records retrieved.');
}

if ($action === 'check-in') {
    requireRole(['Employee']);
    $employeeId = ownEmployee();
    $now = new DateTimeImmutable('now', attendanceTimezone($employeeId));
    $date = $now->format('Y-m-d');
    $checkIn = $now->format('H:i:s');
    $policy = attendancePolicy($employeeId, $date);

    $record = transaction(function () use ($employeeId, $date, $checkIn, $policy): array {
        $employee = record('employees', 'employee_id', $employeeId, true);
        if ($employee['employment_status'] !== 'Active') fail(409, 'Only active employees can check in.');
        if (one('SELECT attendance_id FROM attendance WHERE employee_id=? AND attendance_date=?', [$employeeId, $date])) {
            fail(409, 'You have already checked in or have an attendance record for today.');
        }

        $lateMinutes = 0;
        $status = 'Off Schedule';
        if ($policy['is_working_day']) {
            $scheduled = attendanceTimeMinutes((string) $policy['start_time']);
            $actual = attendanceTimeMinutes($checkIn);
            $lateMinutes = max(0, (int) $actual - ((int) $scheduled + (int) $policy['grace_minutes']));
            $status = $lateMinutes > 0 ? 'Late' : 'Present';
        }

        query(
            'INSERT INTO attendance('
            . 'employee_id,attendance_date,check_in,hours_worked,status,scheduled_start,scheduled_end,'
            . 'expected_hours,late_minutes,early_departure_minutes,attendance_source,verification_status'
            . ') VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $employeeId,
                $date,
                $checkIn,
                '0.00',
                $status,
                $policy['start_time'],
                $policy['end_time'],
                $policy['expected_hours'],
                $lateMinutes,
                0,
                'Self-Service',
                'Unverified',
            ]
        );
        $attendanceId = inserted();
        audit('attendance.check-in', 'attendance', $attendanceId, [
            'employee_id' => $employeeId,
            'status' => $status,
            'verification_status' => 'Unverified',
        ]);
        return record('attendance', 'attendance_id', $attendanceId);
    });

    reply(attendanceRecordResponse($record), 'Check-in recorded. Location has not been verified.', 201);
}

if ($action === 'check-out') {
    requireRole(['Employee']);
    $employeeId = ownEmployee();
    $now = new DateTimeImmutable('now', attendanceTimezone($employeeId));
    $date = $now->format('Y-m-d');
    $checkOut = $now->format('H:i:s');

    $record = transaction(function () use ($employeeId, $date, $checkOut): array {
        $attendance = one(
            'SELECT * FROM attendance WHERE employee_id=? AND attendance_date=? FOR UPDATE',
            [$employeeId, $date]
        ) ?? fail(404, 'Check in before checking out.');
        if ($attendance['check_in'] === null) fail(409, 'Check in before checking out.');
        if ($attendance['check_out'] !== null) fail(409, 'You have already checked out today.');

        $checkInMinutes = attendanceTimeMinutes((string) $attendance['check_in']);
        $checkOutMinutes = attendanceTimeMinutes($checkOut);
        if ($checkOutMinutes <= $checkInMinutes) fail(409, 'Check-out time must be later than check-in time.');

        $hoursWorked = round(($checkOutMinutes - $checkInMinutes) / 60, 2);
        $earlyMinutes = 0;
        $status = $attendance['status'];
        $policy = attendancePolicy($employeeId, $date);

        if ($policy['is_working_day']) {
            $scheduledEnd = attendanceTimeMinutes((string) $attendance['scheduled_end']);
            $earlyMinutes = max(0, ((int) $scheduledEnd - (int) $policy['early_departure_grace_minutes']) - $checkOutMinutes);
            if ($hoursWorked < (float) $policy['minimum_half_day_hours']) {
                $status = 'Half-Day';
            } elseif ((int) $attendance['late_minutes'] > 0) {
                $status = 'Late';
            } elseif ($earlyMinutes > 0) {
                $status = 'Early Checkout';
            } else {
                $status = 'Present';
            }
        }

        query(
            'UPDATE attendance SET check_out=?,hours_worked=?,early_departure_minutes=?,status=? WHERE attendance_id=?',
            [$checkOut, number_format($hoursWorked, 2, '.', ''), $earlyMinutes, $status, (int) $attendance['attendance_id']]
        );
        audit('attendance.check-out', 'attendance', (int) $attendance['attendance_id'], [
            'employee_id' => $employeeId,
            'status' => $status,
            'hours_worked' => $hoursWorked,
        ]);
        return record('attendance', 'attendance_id', (int) $attendance['attendance_id']);
    });

    reply(attendanceRecordResponse($record), 'Check-out recorded and attendance status calculated.');
}

if ($action === 'create') {
    requireRole(['Admin', 'HR']);
    $employeeId = id($body['employee_id'] ?? null, 'employee_id');
    $date = dateValue($body['attendance_date'] ?? null, 'attendance_date');
    $status = choice($body['status'] ?? null, attendanceStatusValues(), 'status');
    $notes = textValue($body['notes'] ?? '', 'notes', 500, false);
    $checkIn = attendanceInputTime($body['check_in'] ?? null, 'check_in');
    $checkOut = attendanceInputTime($body['check_out'] ?? null, 'check_out');
    if ($checkOut !== null && $checkIn === null) fail(400, 'Check-in is required before check-out.');

    $policy = attendancePolicy($employeeId, $date);
    $hours = 0.0;
    if ($checkIn !== null && $checkOut !== null) {
        $start = attendanceTimeMinutes($checkIn);
        $end = attendanceTimeMinutes($checkOut);
        if ($end <= $start) fail(400, 'Check-out must be later than check-in.');
        $hours = round(($end - $start) / 60, 2);
    }

    $attendanceId = transaction(function () use ($employeeId, $date, $checkIn, $checkOut, $hours, $status, $notes, $policy): int {
        record('employees', 'employee_id', $employeeId);
        if (one('SELECT attendance_id FROM attendance WHERE employee_id=? AND attendance_date=?', [$employeeId, $date])) {
            fail(409, 'Attendance already exists for this employee and date.');
        }
        query(
            'INSERT INTO attendance(employee_id,attendance_date,check_in,check_out,hours_worked,status,notes,'
            . 'scheduled_start,scheduled_end,expected_hours,attendance_source,verification_status) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$employeeId, $date, $checkIn, $checkOut, number_format($hours, 2, '.', ''), $status, $notes,
                $policy['start_time'], $policy['end_time'], $policy['expected_hours'], 'Manual', 'Manager Confirmed']
        );
        $id = inserted();
        audit('attendance.manual-create', 'attendance', $id, ['employee_id' => $employeeId, 'status' => $status]);
        return $id;
    });

    reply(attendanceRecordResponse(record('attendance', 'attendance_id', $attendanceId)), 'Attendance record created.', 201);
}

fail(404, 'Unknown attendance action.');

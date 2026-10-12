<?php

declare(strict_types=1);

require_once __DIR__ . '/../hrms/lib/OnboardingAccess.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
    $checks++;
}

foreach ([
    '/api/auth/me.php', '/api/onboarding/get.php', '/api/onboarding/form-submit.php', '/api/documents/submit.php',
    '/api/notifications/get.php', '/api/attendance/check-in.php', '/api/self-service/profile.php',
    '/api/health.php', '/api/configuration/get.php', '/app/hrms/api/onboarding/get.php',
] as $open) {
    check(OnboardingAccess::allows($open), "$open stays open during onboarding");
}

foreach ([
    '/api/Leave/request.php', '/api/leave/types.php', '/api/payroll/get.php', '/api/benefits/get.php',
    '/api/employees/get.php', '/api/performance/goals/get.php', '/api/training/get.php', '/api/progress/get.php',
    '/api/self-service/payroll.php', '/api/self-service/salary.php', '/api/self-service/leave.php',
    '/api/manager/team.php', '/api/users/get.php', '/api/analytics/summary.php', '/api/recruitment/vacancies.php',
    '/api/announcements/create.php', '/api/configuration/leave-type.php',
] as $blocked) {
    check(!OnboardingAccess::allows($blocked), "$blocked is blocked during onboarding");
}

check(!OnboardingAccess::allows('/api/onboarding/../payroll/get.php'), 'path traversal is rejected');
check(!OnboardingAccess::allows('/index.php'), 'non-API scripts are not allowed');
check(!OnboardingAccess::allows(''), 'empty script name is not allowed');

echo "Onboarding access checks passed: {$checks}\n";

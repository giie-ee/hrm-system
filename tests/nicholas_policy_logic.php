<?php

declare(strict_types=1);

require_once __DIR__ . '/../hrms/lib/LeavePolicy.php';

$checks = 0;

function policyCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
    $checks++;
}

policyCheck(LeavePolicy::violation(10, 10, 2, 3) === null, 'a request at both remaining limits is allowed');
policyCheck(
    LeavePolicy::violation(11, 10, 0, 3) === 'This leave type allows at most 10 consecutive days per request.',
    'a request above the consecutive-day limit is rejected'
);
policyCheck(LeavePolicy::violation(2, 10, 2, 3) === null, 'a request below the annual frequency limit is allowed');
policyCheck(
    LeavePolicy::violation(2, 10, 3, 3) === 'This leave type allows at most 3 requests per calendar year.',
    'a request at the consumed annual frequency limit is rejected'
);
policyCheck(LeavePolicy::violation(30, null, 20, null) === null, 'custom leave types may omit both optional limits');
policyCheck(
    LeavePolicy::violation(91, 90, 0, 1) !== null,
    'the maternity demonstration policy rejects more than 90 consecutive days'
);

echo "Nicholas policy checks passed: {$checks}" . PHP_EOL;

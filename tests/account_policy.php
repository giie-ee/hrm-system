<?php

declare(strict_types=1);

require_once __DIR__ . '/../hrms/lib/AccountPolicy.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
    $checks++;
}

foreach (['Abcdef1!gh', 'Zm9$aaaaaaaa', 'Str0ng#Passw0rd'] as $good) check(AccountPolicy::isStrongPassword($good), "$good is accepted");
foreach (['short1!A', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoDigitsHere!!', 'NoSymbol12345', '', str_repeat('Aa1!', 40)] as $bad) {
    check(!AccountPolicy::isStrongPassword($bad), 'weak/oversized password is rejected');
}

$seen = [];
for ($i = 0; $i < 200; $i++) {
    $p = AccountPolicy::generateTemporaryPassword();
    check(strlen($p) === 14, 'temporary password is 14 characters');
    check(AccountPolicy::isStrongPassword($p), 'temporary password meets the policy');
    $seen[$p] = true;
}
check(count($seen) === 200, 'temporary passwords are unique');

check(AccountPolicy::baseUsername('Nia', 'Hire') === 'nia.hire', 'username is first.last');
check(AccountPolicy::baseUsername("O'Brien", 'Mwansa-Phiri') === 'obrien.mwansaphiri', 'punctuation is stripped');
check(strlen(AccountPolicy::baseUsername('X', '')) >= 3, 'very short names are padded to 3 characters');
check(preg_match('/^[A-Za-z0-9_.-]{3,50}$/D', AccountPolicy::baseUsername(str_repeat('a', 80), str_repeat('b', 80))) === 1, 'long names fit the username rules');

check(AccountPolicy::allowedWhilePasswordChangePending('/api/auth/change-password.php'), 'change-password is reachable');
check(AccountPolicy::allowedWhilePasswordChangePending('/api/auth/me.php'), 'me is reachable');
foreach (['/api/Leave/types.php', '/api/onboarding/get.php', '/api/payroll/get.php', '/api/auth/../payroll/get.php', ''] as $blocked) {
    check(!AccountPolicy::allowedWhilePasswordChangePending($blocked), "$blocked is blocked while the password change is pending");
}

echo "Account policy checks passed: {$checks}\n";

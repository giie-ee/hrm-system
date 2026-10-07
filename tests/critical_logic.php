<?php

declare(strict_types=1);

require_once __DIR__ . '/../hrms/lib/SensitiveData.php';
require_once __DIR__ . '/../hrms/lib/OnboardingFormPolicy.php';

$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
    $checks++;
}

function expectFailure(callable $work, string $message): void
{
    try {
        $work();
    } catch (Throwable) {
        check(true, $message);
        return;
    }
    throw new RuntimeException('FAILED: ' . $message);
}

$originalActive = getenv('HRMS_BANKING_ACTIVE_KEY_ID');
$originalKeys = getenv('HRMS_BANKING_KEYS_JSON');

try {
    $key = random_bytes(32);
    putenv('HRMS_BANKING_ACTIVE_KEY_ID=test-key');
    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode($key)], JSON_THROW_ON_ERROR));

    $bankName = 'Example Bank';
    $account = 'ACC-123456789';
    $first = SensitiveData::protectBanking($bankName, $account, 41, 12);
    $second = SensitiveData::protectBanking($bankName, $account, 41, 12);
    check($first !== $second, 'encryption uses a fresh random IV');
    check(!str_contains($first, $bankName) && !str_contains($first, $account), 'ciphertext does not expose plaintext');
    check(SensitiveData::revealBanking($first, 41, 12) === ['bank_name' => $bankName, 'bank_account_number' => $account], 'valid ciphertext decrypts in its owning context');
    expectFailure(fn () => SensitiveData::revealBanking($first, 42, 12), 'ciphertext cannot be copied to another onboarding record');

    $tampered = json_decode($first, true, 16, JSON_THROW_ON_ERROR);
    $tampered['ciphertext'] = base64_encode(base64_decode($tampered['ciphertext'], true) . 'x');
    expectFailure(fn () => SensitiveData::revealBanking($tampered, 41, 12), 'tampered ciphertext is rejected');

    $unknown = json_decode($first, true, 16, JSON_THROW_ON_ERROR);
    $unknown['key_id'] = 'retired-key';
    expectFailure(fn () => SensitiveData::revealBanking($unknown, 41, 12), 'unknown key IDs fail closed');

    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode(random_bytes(32))], JSON_THROW_ON_ERROR));
    expectFailure(fn () => SensitiveData::revealBanking($first, 41, 12), 'the wrong key cannot decrypt banking data');
    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode($key)], JSON_THROW_ON_ERROR));

    $oldKey = random_bytes(32);
    putenv('HRMS_BANKING_ACTIVE_KEY_ID=old-key');
    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['old-key' => base64_encode($oldKey)], JSON_THROW_ON_ERROR));
    $oldEnvelope = SensitiveData::protectBanking($bankName, $account, 51, 12);
    putenv('HRMS_BANKING_ACTIVE_KEY_ID=test-key');
    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode($key), 'old-key' => base64_encode($oldKey)], JSON_THROW_ON_ERROR));
    check(SensitiveData::revealBanking($oldEnvelope, 51, 12)['bank_account_number'] === $account, 'retained old keys decrypt historical envelopes after rotation');
    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode($key)], JSON_THROW_ON_ERROR));
    expectFailure(fn () => SensitiveData::revealBanking($oldEnvelope, 51, 12), 'removing an old key makes historical envelopes fail closed');

    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode('short')], JSON_THROW_ON_ERROR));
    expectFailure(fn () => SensitiveData::assertBankingKeysConfigured(), 'malformed keys fail before use');
    putenv('HRMS_BANKING_KEYS_JSON=' . json_encode(['test-key' => base64_encode($key)], JSON_THROW_ON_ERROR));

    check(OnboardingFormPolicy::mayRead('Employee', 12, 12), 'employees can read their own non-banking form data');
    check(!OnboardingFormPolicy::mayRead('Employee', 12, 13), 'employees cannot read another employee form');
    check(!OnboardingFormPolicy::mayRead('Manager', 20, 12), 'managers cannot read personal onboarding forms');
    check(OnboardingFormPolicy::mayRead('Manager', 20, 20), 'manager employees can read their own onboarding form');
    check(OnboardingFormPolicy::mayRead('HR', 20, 12), 'HR can read non-banking onboarding form data');
    check(OnboardingFormPolicy::mayReview('Admin', 20, 12), 'Admin can review another employee form');
    check(!OnboardingFormPolicy::mayReview('Manager', 20, 12), 'managers cannot review onboarding forms');
    check(!OnboardingFormPolicy::mayReview('HR', 12, 12), 'reviewers cannot approve their own form');

    $redacted = OnboardingFormPolicy::redactBanking(['bank_name' => $bankName, 'bank_account_number' => $account, 'phone' => '+260000000'], true);
    check(
        !array_key_exists('bank_name', $redacted) && !array_key_exists('bank_account_number', $redacted),
        'ordinary API data removes banking values'
    );
    check($redacted['banking_submitted'] === true && $redacted['phone'] === '+260000000', 'ordinary API data preserves non-banking fields and a neutral receipt flag');

    echo "Critical logic checks passed: {$checks}" . PHP_EOL;
} finally {
    putenv($originalActive === false ? 'HRMS_BANKING_ACTIVE_KEY_ID' : 'HRMS_BANKING_ACTIVE_KEY_ID=' . $originalActive);
    putenv($originalKeys === false ? 'HRMS_BANKING_KEYS_JSON' : 'HRMS_BANKING_KEYS_JSON=' . $originalKeys);
}

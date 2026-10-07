<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

$projectRoot = dirname(__DIR__);
$backendRoot = (string) (getenv('HRMS_BACKEND_ROOT') ?: $projectRoot . '/hrms');
require_once $backendRoot . '/config/database.php';
require_once $backendRoot . '/lib/SensitiveData.php';

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
    echo "Sensitive onboarding backfill skipped: PostgreSQL is not in use.\n";
    exit(0);
}
SensitiveData::assertBankingKeysConfigured();

try {
    $pdo->beginTransaction();
    $rows = $pdo->query(
        "SELECT form_id,onboarding_id,employee_id,form_data::text "
        . "FROM onboarding_forms "
        . "WHERE form_data ?| ARRAY['bank_name','bank_account_number'] "
        . "ORDER BY form_id FOR UPDATE"
    )->fetchAll();
    $update = $pdo->prepare(
        "UPDATE onboarding_forms SET banking_envelope=CAST(? AS JSONB),"
        . "form_data=form_data-'bank_name'-'bank_account_number' WHERE form_id=?"
    );

    foreach ($rows as $row) {
        $data = json_decode((string) $row['form_data'], true, 32, JSON_THROW_ON_ERROR);
        $bankName = $data['bank_name'] ?? null;
        $account = $data['bank_account_number'] ?? null;
        if (!is_string($bankName) || trim($bankName) === ''
            || !is_string($account) || trim($account) === '') {
            throw new RuntimeException(
                'Onboarding form ' . (int) $row['form_id'] . ' contains incomplete plaintext banking data.'
            );
        }
        $envelope = SensitiveData::protectBanking(
            trim($bankName),
            trim($account),
            (int) $row['onboarding_id'],
            (int) $row['employee_id']
        );
        $update->execute([$envelope, (int) $row['form_id']]);
    }

    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM onboarding_forms "
        . "WHERE form_data ?| ARRAY['bank_name','bank_account_number']"
    )->fetchColumn();
    if ($remaining !== 0) {
        throw new RuntimeException('Plaintext onboarding banking data remains after the backfill.');
    }

    $envelopes = $pdo->query(
        "SELECT form_id,onboarding_id,employee_id,banking_envelope::text "
        . "FROM onboarding_forms WHERE banking_envelope IS NOT NULL ORDER BY form_id"
    );
    $verified = 0;
    while ($row = $envelopes->fetch()) {
        SensitiveData::revealBanking(
            (string) $row['banking_envelope'],
            (int) $row['onboarding_id'],
            (int) $row['employee_id']
        );
        $verified++;
    }
    $missing = (int) $pdo->query(
        "SELECT COUNT(*) FROM onboarding_forms "
        . "WHERE status IN ('Submitted','Approved','Rejected') AND banking_envelope IS NULL"
    )->fetchColumn();
    if ($missing !== 0) {
        throw new RuntimeException('One or more submitted onboarding forms have no protected banking envelope.');
    }
    $pdo->exec(
        'ALTER TABLE onboarding_forms VALIDATE CONSTRAINT onboarding_forms_no_plaintext_banking'
    );
    $pdo->commit();
    echo 'Sensitive onboarding banking data protected. Rows converted: ' . count($rows)
        . '; envelopes verified: ' . $verified . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Sensitive onboarding backfill failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

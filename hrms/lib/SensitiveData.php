<?php

declare(strict_types=1);

/**
 * Authenticated application-level encryption for sensitive onboarding data.
 *
 * Keys live only in environment variables. Ciphertext is bound to the owning
 * onboarding and employee IDs, so copying it to another record fails closed.
 */
final class SensitiveData
{
    private const CIPHER = 'aes-256-gcm';
    private const ENVELOPE_VERSION = 1;
    private const TAG_BYTES = 16;

    public static function assertBankingKeysConfigured(): void
    {
        self::activeBankingKey();
    }

    public static function bankingAad(int $onboardingId, int $employeeId): string
    {
        if ($onboardingId < 1 || $employeeId < 1) {
            throw new InvalidArgumentException('Banking encryption context is invalid.');
        }
        return "hrms:onboarding-banking:v1:{$onboardingId}:{$employeeId}";
    }

    public static function protectBanking(
        #[\SensitiveParameter] string $bankName,
        #[\SensitiveParameter] string $accountNumber,
        int $onboardingId,
        int $employeeId
    ): string {
        [$keyId, $key] = self::activeBankingKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false || $ivLength < 12) {
            throw new RuntimeException('The configured encryption cipher is unavailable.');
        }

        $iv = random_bytes($ivLength);
        $plaintext = json_encode(
            ['bank_name' => $bankName, 'bank_account_number' => $accountNumber],
            JSON_THROW_ON_ERROR
        );
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::bankingAad($onboardingId, $employeeId),
            self::TAG_BYTES
        );
        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Sensitive banking information could not be protected.');
        }

        return json_encode([
            'version' => self::ENVELOPE_VERSION,
            'key_id' => $keyId,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR);
    }

    /** @return array{bank_name:string,bank_account_number:string} */
    public static function revealBanking(
        #[\SensitiveParameter] string|array $encodedEnvelope,
        int $onboardingId,
        int $employeeId
    ): array {
        $envelope = is_array($encodedEnvelope)
            ? $encodedEnvelope
            : json_decode($encodedEnvelope, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($envelope)
            || (int) ($envelope['version'] ?? 0) !== self::ENVELOPE_VERSION
            || !is_string($envelope['key_id'] ?? null)
            || !is_string($envelope['iv'] ?? null)
            || !is_string($envelope['tag'] ?? null)
            || !is_string($envelope['ciphertext'] ?? null)) {
            throw new RuntimeException('Sensitive banking information is invalid.');
        }

        $keys = self::bankingKeys();
        $key = $keys[$envelope['key_id']] ?? null;
        if (!is_string($key)) {
            throw new RuntimeException('Sensitive banking information uses an unavailable key.');
        }
        $iv = base64_decode($envelope['iv'], true);
        $tag = base64_decode($envelope['tag'], true);
        $ciphertext = base64_decode($envelope['ciphertext'], true);
        if ($iv === false || $tag === false || $ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Sensitive banking information is invalid.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::bankingAad($onboardingId, $employeeId)
        );
        if ($plaintext === false) {
            throw new RuntimeException('Sensitive banking information could not be authenticated.');
        }
        $banking = json_decode($plaintext, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($banking)
            || !is_string($banking['bank_name'] ?? null)
            || !is_string($banking['bank_account_number'] ?? null)) {
            throw new RuntimeException('Sensitive banking information is invalid.');
        }
        return [
            'bank_name' => $banking['bank_name'],
            'bank_account_number' => $banking['bank_account_number'],
        ];
    }

    /** @return array{0:string,1:string} */
    private static function activeBankingKey(): array
    {
        $activeId = trim((string) getenv('HRMS_BANKING_ACTIVE_KEY_ID'));
        if (!preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $activeId)) {
            throw new RuntimeException('HRMS_BANKING_ACTIVE_KEY_ID is not configured correctly.');
        }
        $keys = self::bankingKeys();
        if (!isset($keys[$activeId])) {
            throw new RuntimeException('The active banking encryption key is unavailable.');
        }
        return [$activeId, $keys[$activeId]];
    }

    /** @return array<string,string> */
    private static function bankingKeys(): array
    {
        $raw = trim((string) getenv('HRMS_BANKING_KEYS_JSON'));
        if ($raw === '') {
            throw new RuntimeException('HRMS_BANKING_KEYS_JSON is not configured.');
        }
        try {
            $configured = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('HRMS_BANKING_KEYS_JSON is not valid JSON.', 0, $exception);
        }
        if (!is_array($configured) || $configured === []) {
            throw new RuntimeException('HRMS_BANKING_KEYS_JSON must contain at least one key.');
        }

        $keys = [];
        foreach ($configured as $keyId => $encodedKey) {
            if (!is_string($keyId)
                || !preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $keyId)
                || !is_string($encodedKey)) {
                throw new RuntimeException('A banking encryption key entry is invalid.');
            }
            $key = base64_decode($encodedKey, true);
            if ($key === false || strlen($key) !== 32) {
                throw new RuntimeException("Banking encryption key '{$keyId}' must be 32 bytes encoded as base64.");
            }
            $keys[$keyId] = $key;
        }
        return $keys;
    }
}

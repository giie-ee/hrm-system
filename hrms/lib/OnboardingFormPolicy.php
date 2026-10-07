<?php

declare(strict_types=1);

final class OnboardingFormPolicy
{
    public static function mayRead(string $role, int $currentEmployeeId, int $targetEmployeeId): bool
    {
        return in_array($role, ['Admin', 'HR'], true)
            || $currentEmployeeId === $targetEmployeeId;
    }

    public static function mayReview(string $role, int $currentEmployeeId, int $targetEmployeeId): bool
    {
        return in_array($role, ['Admin', 'HR'], true)
            && $currentEmployeeId !== $targetEmployeeId;
    }

    public static function redactBanking(array $formData, bool $bankingSubmitted): array
    {
        unset($formData['bank_name'], $formData['bank_account_number']);
        $formData['banking_submitted'] = $bankingSubmitted;
        return $formData;
    }
}

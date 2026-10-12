<?php

declare(strict_types=1);

/**
 * Endpoint allowlist for Employees who have not finished onboarding.
 *
 * Everything not listed is blocked (default deny), so new modules are
 * restricted automatically. Admin, HR and Manager accounts are never gated.
 */
final class OnboardingAccess
{
    public const ERROR_CODE = 'ONBOARDING_REQUIRED';
    public const MESSAGE = 'Complete your onboarding to access this feature.';

    /** Directory prefixes (lower case, relative to the API root) that stay open. */
    private const OPEN_PREFIXES = [
        'api/auth/',           // login, logout, current session
        'api/onboarding/',     // onboarding record, documents list, information form
        'api/documents/',      // upload / download requested documents
        'api/notifications/',
        'api/attendance/',     // day-one check-in and own attendance
    ];

    /** Individual endpoints that stay open. */
    private const OPEN_FILES = [
        'api/health.php',
        'api/announcements/get.php',
        'api/configuration/get.php',
        'api/configuration/work-policy.php',
        'api/self-service/profile.php',
        'api/self-service/update-profile.php',
        'api/self-service/onboarding.php',
        'api/self-service/attendance.php',
    ];

    public static function allows(string $scriptName): bool
    {
        $path = strtolower(str_replace('\\', '/', $scriptName));
        $position = strpos($path, '/api/');
        if ($position === false || str_contains($path, '..')) return false;
        $path = substr($path, $position + 1);

        if (in_array($path, self::OPEN_FILES, true)) return true;
        foreach (self::OPEN_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }
}

<?php

declare(strict_types=1);

/** Password rules, temporary-password generation and username derivation. */
final class AccountPolicy
{
    public const PASSWORD_CHANGE_CODE = 'PASSWORD_CHANGE_REQUIRED';
    public const PASSWORD_CHANGE_MESSAGE = 'Change your temporary password to continue.';
    public const PASSWORD_RULE = 'Password requires at least 10 characters, upper/lowercase, number and symbol.';

    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 10 && strlen($password) <= 128
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
    }

    /** Random 14 character password that always satisfies isStrongPassword(). */
    public static function generateTemporaryPassword(): string
    {
        $pools = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%*?+-'];
        $all = implode('', $pools);
        $chars = [];
        foreach ($pools as $pool) $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        while (count($chars) < 14) $chars[] = $all[random_int(0, strlen($all) - 1)];
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    /** first.last, lower case, ASCII only, 3-50 characters. */
    public static function baseUsername(string $first, string $last): string
    {
        $clean = static fn (string $s): string => preg_replace('/[^a-z0-9]/', '', strtolower($s)) ?? '';
        $name = $clean($first) . '.' . $clean($last);
        $name = trim($name, '.');
        if (strlen($name) < 3) $name = str_pad($name, 3, 'x');
        return substr($name, 0, 40);
    }

    /** While a password change is pending only the auth endpoints are reachable. */
    public static function allowedWhilePasswordChangePending(string $scriptName): bool
    {
        $path = strtolower(str_replace(chr(92), '/', $scriptName));
        $position = strpos($path, '/api/');
        if ($position === false || str_contains($path, '..')) return false;
        return str_starts_with(substr($path, $position + 1), 'api/auth/');
    }
}

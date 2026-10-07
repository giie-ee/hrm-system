<?php

declare(strict_types=1);

final class LeavePolicy
{
    public static function violation(
        int $requestedDays,
        ?int $maxConsecutiveDays,
        int $existingRequestsThisYear,
        ?int $maxRequestsPerYear
    ): ?string {
        if ($maxConsecutiveDays !== null && $requestedDays > $maxConsecutiveDays) {
            return "This leave type allows at most {$maxConsecutiveDays} consecutive days per request.";
        }
        if ($maxRequestsPerYear !== null && $existingRequestsThisYear >= $maxRequestsPerYear) {
            return "This leave type allows at most {$maxRequestsPerYear} requests per calendar year.";
        }
        return null;
    }
}

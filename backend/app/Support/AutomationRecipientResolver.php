<?php

namespace App\Support;

use App\Models\InternshipApplication;
use App\Models\User;

/**
 * Resolves who should receive an automation. Every address is a controlled
 * test address from seeded demo data — never a real institutional mailbox.
 */
final class AutomationRecipientResolver
{
    /**
     * @return array{0: string, 1: string|null}
     */
    public static function forMilestone(InternshipApplication $application, string $toStatus): array
    {
        return match ($toStatus) {
            InternshipApplication::STATUS_SUBMITTED => self::adviser($application),
            InternshipApplication::STATUS_PARTNER_SUBMITTED => self::partnerReviewer($application),
            InternshipApplication::STATUS_PLACEMENT_CONFIRMED => self::student($application),
            default => self::coordinator(),
        };
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function student(InternshipApplication $application): array
    {
        return [User::ROLE_STUDENT, $application->student?->email ?? self::fallback()];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function adviser(InternshipApplication $application): array
    {
        return [User::ROLE_ADVISER, $application->adviser?->email ?? self::fallback()];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function partnerReviewer(InternshipApplication $application): array
    {
        return [User::ROLE_PARTNER_REVIEWER, $application->partnerReviewer?->email ?? self::fallback()];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function coordinator(): array
    {
        return [User::ROLE_COORDINATOR, self::coordinatorEmail()];
    }

    public static function coordinatorEmail(): ?string
    {
        $email = User::query()
            ->where('role', User::ROLE_COORDINATOR)
            ->orderBy('id')
            ->value('email');

        return filled($email) ? $email : self::fallback();
    }

    public static function fallback(): ?string
    {
        $fallback = config('automation.fallback_recipient');

        return filled($fallback) ? $fallback : null;
    }
}
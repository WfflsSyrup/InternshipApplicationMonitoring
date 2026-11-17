<?php

namespace App\Support;

use App\Models\InternshipApplication;
use App\Models\User;

class ApplicationStatusMachine
{
    /**
     * @var array<string, array<string, array<int, string>>>
     */
    public const TRANSITIONS = [
        InternshipApplication::STATUS_DRAFT => [
            InternshipApplication::STATUS_SUBMITTED => [User::ROLE_STUDENT],
        ],
        InternshipApplication::STATUS_SUBMITTED => [
            InternshipApplication::STATUS_REQUIREMENTS_VERIFIED => [User::ROLE_ADVISER, User::ROLE_COORDINATOR],
            InternshipApplication::STATUS_REVISION_REQUESTED => [User::ROLE_ADVISER, User::ROLE_COORDINATOR],
        ],
        InternshipApplication::STATUS_REVISION_REQUESTED => [
            InternshipApplication::STATUS_SUBMITTED => [User::ROLE_STUDENT],
        ],
        InternshipApplication::STATUS_REQUIREMENTS_VERIFIED => [
            InternshipApplication::STATUS_ENDORSED => [User::ROLE_ADVISER, User::ROLE_COORDINATOR],
        ],
        InternshipApplication::STATUS_ENDORSED => [
            InternshipApplication::STATUS_PARTNER_SUBMITTED => [User::ROLE_COORDINATOR],
        ],
        InternshipApplication::STATUS_PARTNER_SUBMITTED => [
            InternshipApplication::STATUS_INTERVIEW_SCHEDULED => [User::ROLE_PARTNER_REVIEWER, User::ROLE_COORDINATOR],
        ],
        InternshipApplication::STATUS_INTERVIEW_SCHEDULED => [
            InternshipApplication::STATUS_INTERVIEW_PASSED => [User::ROLE_PARTNER_REVIEWER],
            InternshipApplication::STATUS_INTERVIEW_FAILED => [User::ROLE_PARTNER_REVIEWER],
        ],
        InternshipApplication::STATUS_INTERVIEW_PASSED => [
            InternshipApplication::STATUS_PLACEMENT_CONFIRMED => [User::ROLE_COORDINATOR],
        ],
        InternshipApplication::STATUS_PLACEMENT_CONFIRMED => [
            InternshipApplication::STATUS_PLACEMENT_CONFIRMED => [User::ROLE_STUDENT],
        ],
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        InternshipApplication::STATUS_DRAFT => 'Draft',
        InternshipApplication::STATUS_SUBMITTED => 'Submitted',
        InternshipApplication::STATUS_REQUIREMENTS_VERIFIED => 'Requirements Verified',
        InternshipApplication::STATUS_REVISION_REQUESTED => 'Revision Requested',
        InternshipApplication::STATUS_ENDORSED => 'Endorsed',
        InternshipApplication::STATUS_PARTNER_SUBMITTED => 'Submitted to Partner',
        InternshipApplication::STATUS_INTERVIEW_SCHEDULED => 'Interview Scheduled',
        InternshipApplication::STATUS_INTERVIEW_PASSED => 'Interview Passed',
        InternshipApplication::STATUS_INTERVIEW_FAILED => 'Interview Failed',
        InternshipApplication::STATUS_PLACEMENT_CONFIRMED => 'Placement Confirmed',
        InternshipApplication::STATUS_WITHDRAWN => 'Withdrawn',
        InternshipApplication::STATUS_REJECTED => 'Rejected',
    ];

    /**
     * @return array<int, string>
     */
    public static function rolesFor(string $from, string $target): array
    {
        if ($target === InternshipApplication::STATUS_WITHDRAWN && self::isWithdrawable($from)) {
            return [User::ROLE_STUDENT];
        }

        if ($target === InternshipApplication::STATUS_REJECTED && self::isActive($from)) {
            return [User::ROLE_COORDINATOR, User::ROLE_PARTNER_REVIEWER];
        }

        return self::TRANSITIONS[$from][$target] ?? [];
    }

    public static function canTransition(string $from, string $target, string $role): bool
    {
        return in_array($role, self::rolesFor($from, $target), true);
    }

    /**
     * @return array<int, string>
     */
    public static function targetsFor(string $status, string $role): array
    {
        $targets = array_keys(self::TRANSITIONS[$status] ?? []);

        if (self::isWithdrawable($status)) {
            $targets[] = InternshipApplication::STATUS_WITHDRAWN;
        }

        if (self::isActive($status)) {
            $targets[] = InternshipApplication::STATUS_REJECTED;
        }

        $allowed = [];

        foreach (array_unique($targets) as $target) {
            if (self::canTransition($status, $target, $role)) {
                $allowed[] = $target;
            }
        }

        return $allowed;
    }

    public static function isActive(string $status): bool
    {
        return in_array($status, InternshipApplication::ACTIVE_STATUSES, true);
    }

    public static function isWithdrawable(string $status): bool
    {
        return self::isActive($status)
            && $status !== InternshipApplication::STATUS_PLACEMENT_CONFIRMED;
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [
            InternshipApplication::STATUS_WITHDRAWN,
            InternshipApplication::STATUS_REJECTED,
            InternshipApplication::STATUS_PLACEMENT_CONFIRMED,
        ], true);
    }

    public static function requiresRemarks(string $target): bool
    {
        return in_array($target, [
            InternshipApplication::STATUS_REVISION_REQUESTED,
            InternshipApplication::STATUS_REJECTED,
        ], true);
    }

    public static function requiresPartnerReviewer(string $target): bool
    {
        return $target === InternshipApplication::STATUS_PARTNER_SUBMITTED;
    }

    public static function requiresInterviewSchedule(string $target): bool
    {
        return $target === InternshipApplication::STATUS_INTERVIEW_SCHEDULED;
    }

    public static function isAcknowledgement(string $from, string $target): bool
    {
        return $from === InternshipApplication::STATUS_PLACEMENT_CONFIRMED
            && $target === InternshipApplication::STATUS_PLACEMENT_CONFIRMED;
    }

    /**
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        InternshipApplication::STATUS_SUBMITTED => 'Submit Application',
        InternshipApplication::STATUS_REQUIREMENTS_VERIFIED => 'Verify Requirements',
        InternshipApplication::STATUS_REVISION_REQUESTED => 'Request Revision',
        InternshipApplication::STATUS_ENDORSED => 'Endorse Application',
        InternshipApplication::STATUS_PARTNER_SUBMITTED => 'Submit To Partner',
        InternshipApplication::STATUS_INTERVIEW_SCHEDULED => 'Schedule Interview',
        InternshipApplication::STATUS_INTERVIEW_PASSED => 'Record Passed Interview',
        InternshipApplication::STATUS_INTERVIEW_FAILED => 'Record Failed Interview',
        InternshipApplication::STATUS_PLACEMENT_CONFIRMED => 'Confirm Placement',
        InternshipApplication::STATUS_WITHDRAWN => 'Withdraw Application',
        InternshipApplication::STATUS_REJECTED => 'Reject Application',
    ];

    public static function actionLabel(string $from, string $target, string $role): string
    {
        if (self::isAcknowledgement($from, $target)) {
            return 'Acknowledge Placement';
        }

        return self::ACTION_LABELS[$target] ?? self::label($target);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? str_replace('_', ' ', ucfirst($status));
    }
}
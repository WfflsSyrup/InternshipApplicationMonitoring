<?php

namespace App\Policies;

use App\Models\InternshipApplication;
use App\Models\User;

class InternshipApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InternshipApplication $application): bool
    {
        return match ($user->role) {
            User::ROLE_COORDINATOR => true,
            User::ROLE_STUDENT => $application->student_id === $user->getKey(),
            User::ROLE_ADVISER => $this->isAdviserFor($user, $application),
            User::ROLE_PARTNER_REVIEWER => $application->partner_reviewer_id === $user->getKey()
                && in_array($application->status, InternshipApplication::PARTNER_VISIBLE_STATUSES, true),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    public function update(User $user, InternshipApplication $application): bool
    {
        return $user->isStudent()
            && $application->student_id === $user->getKey()
            && $application->isEditableByStudent();
    }

    public function transition(User $user, InternshipApplication $application): bool
    {
        return match ($user->role) {
            User::ROLE_COORDINATOR => true,
            User::ROLE_STUDENT => $application->student_id === $user->getKey(),
            User::ROLE_ADVISER => $this->isAdviserFor($user, $application),
            User::ROLE_PARTNER_REVIEWER => $application->partner_reviewer_id === $user->getKey(),
            default => false,
        };
    }

    public function delete(User $user, InternshipApplication $application): bool
    {
        return false;
    }

    private function isAdviserFor(User $user, InternshipApplication $application): bool
    {
        if ($application->adviser_id === $user->getKey()) {
            return true;
        }

        if (blank($user->department_or_company)) {
            return false;
        }

        return $application->student !== null
            && $application->student->department_or_company === $user->department_or_company;
    }
}
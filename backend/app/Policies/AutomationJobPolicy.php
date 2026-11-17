<?php

namespace App\Policies;

use App\Models\AutomationJob;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AutomationJobPolicy
{
    /**
     * Listing is open to every authenticated role; the query itself is scoped
     * by AutomationJob::scopeVisibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AutomationJob $job): bool
    {
        if ($user->isCoordinator()) {
            return true;
        }

        if ($job->internship_application_id === null) {
            return false;
        }

        $application = $job->application;

        return $application !== null
            && Gate::forUser($user)->allows('view', $application);
    }

    /**
     * Coordinators only: run sweeps and requeue parked jobs.
     */
    public function manage(User $user): bool
    {
        return $user->isCoordinator();
    }
}
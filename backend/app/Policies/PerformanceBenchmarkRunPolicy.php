<?php

namespace App\Policies;

use App\Models\PerformanceBenchmarkRun;
use App\Models\User;

class PerformanceBenchmarkRunPolicy
{
    /**
     * Performance benchmark telemetry is coordinator-only.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }
}
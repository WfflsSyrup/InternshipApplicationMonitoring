<?php

namespace App\Policies;

use App\Models\User;

class SecurityAuditEventPolicy
{
    /**
     * Security telemetry is coordinator-only: it exposes endpoint names,
     * roles and hashed client identifiers.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }
}
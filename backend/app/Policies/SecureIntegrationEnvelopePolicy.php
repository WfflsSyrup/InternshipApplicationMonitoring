<?php

namespace App\Policies;

use App\Models\SecureIntegrationEnvelope;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SecureIntegrationEnvelopePolicy
{
    /**
     * Listing is open to every authenticated role; the query is scoped by
     * SecureIntegrationEnvelope::scopeVisibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SecureIntegrationEnvelope $envelope): bool
    {
        if ($user->isCoordinator()) {
            return true;
        }

        if ($envelope->internship_application_id === null) {
            return false;
        }

        $application = $envelope->application;

        return $application !== null
            && Gate::forUser($user)->allows('view', $application);
    }

    /**
     * Coordinator-only: seal partner packets, run the verification lab and
     * inspect the keyring status.
     */
    public function manage(User $user): bool
    {
        return $user->isCoordinator();
    }
}
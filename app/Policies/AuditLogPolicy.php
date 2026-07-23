<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * UC-34: Admin and Auditor can view the full audit log.
     * Scheduler/Manager can view audit log entries within their scope.
     */
    public function viewAny(User $user): bool
    {
        return $user->canActAs(Role::ADMIN)
            || $user->canActAs(Role::AUDITOR)
            || $user->canActAs(Role::SCHEDULER_MANAGER);
    }
}

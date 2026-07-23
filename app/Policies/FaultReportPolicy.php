<?php

namespace App\Policies;

use App\Models\FaultReport;
use App\Models\Role;
use App\Models\User;

class FaultReportPolicy
{
    /**
     * UC-18: Any authenticated user can report a fault.
     * Auditors cannot — they are read-only.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::ENGINEER)
            || $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-19: Technician, Scheduler/Manager, Admin can triage and update
     * fault report status (Open → In Progress → Resolved).
     */
    public function update(User $user, FaultReport $faultReport): bool
    {
        return $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * Any authenticated user can view fault reports.
     */
    public function view(User $user, FaultReport $faultReport): bool
    {
        return true;
    }
}

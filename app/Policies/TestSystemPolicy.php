<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\TestSystem;
use App\Models\User;

class TestSystemPolicy
{
    /**
     * UC-9: Any authenticated user can view a test system and its details.
     */
    public function view(User $user, TestSystem $testSystem): bool
    {
        return true;
    }

    /**
     * UC-5: Technician, Scheduler/Manager, Admin can create test systems.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-5: Same roles can edit test system attributes.
     */
    public function update(User $user, TestSystem $testSystem): bool
    {
        return $this->create($user);
    }
}

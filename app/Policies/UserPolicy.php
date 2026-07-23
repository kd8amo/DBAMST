<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * UC-32: Only Admin can create users.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-32: Only Admin can edit users and assign roles.
     */
    public function update(User $user, User $target): bool
    {
        return $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-32: Only Admin can deactivate users.
     */
    public function deactivate(User $user, User $target): bool
    {
        return $user->canActAs(Role::ADMIN);
    }

    /**
     * Any authenticated user can view their own profile.
     * Admin can view any user profile.
     */
    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-34: Admin, Scheduler/Manager, and Auditor can view the full user list.
     */
    public function viewAny(User $user): bool
    {
        return $user->canActAs(Role::ADMIN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::AUDITOR);
    }
}

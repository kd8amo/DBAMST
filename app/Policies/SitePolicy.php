<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    /**
     * Any authenticated user can view sites — site is a filter dimension,
     * not an access boundary.
     */
    public function view(User $user, Site $site): bool
    {
        return true;
    }

    /**
     * Only Admin can create or update sites.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::ADMIN);
    }

    public function update(User $user, Site $site): bool
    {
        return $user->canActAs(Role::ADMIN);
    }
}

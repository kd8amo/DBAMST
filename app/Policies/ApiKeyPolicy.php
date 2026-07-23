<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\Role;
use App\Models\User;

class ApiKeyPolicy
{
    /**
     * UC-33: Only Admin can create API keys.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-33: Only Admin can view the list of API keys.
     */
    public function viewAny(User $user): bool
    {
        return $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-33: Only Admin can revoke an API key.
     */
    public function revoke(User $user, ApiKey $apiKey): bool
    {
        return $user->canActAs(Role::ADMIN);
    }
}

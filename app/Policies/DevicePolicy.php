<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\Role;
use App\Models\User;

class DevicePolicy
{
    /**
     * UC-8: Any authenticated user can view a device and its full history.
     */
    public function view(User $user, Device $device): bool
    {
        return true;
    }

    /**
     * UC-2/UC-1: Technician, Scheduler/Manager, Admin can create devices.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-3: Same roles can edit device attributes.
     */
    public function update(User $user, Device $device): bool
    {
        return $this->create($user);
    }

    /**
     * UC-4: Same roles can retire a device.
     */
    public function retire(User $user, Device $device): bool
    {
        return $this->create($user);
    }

    /**
     * UC-6/UC-7: Technician, Scheduler/Manager, Admin can assign/unassign devices.
     */
    public function assign(User $user, Device $device): bool
    {
        return $this->create($user);
    }

    /**
     * UC-10: Technician, Scheduler/Manager, Admin can transfer a device between sites.
     */
    public function transfer(User $user, Device $device): bool
    {
        return $this->create($user);
    }

    /**
     * UC-10/UC-23: Only Scheduler/Manager and Admin may override a transfer conflict.
     */
    public function overrideConflict(User $user, Device $device): bool
    {
        return $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-11: Technician, Scheduler/Manager, Admin can generate/print asset labels.
     */
    public function printLabel(User $user, Device $device): bool
    {
        return $this->create($user);
    }

    /**
     * UC-2: Technician, Scheduler/Manager, Admin can bulk import devices.
     */
    public function bulkImport(User $user): bool
    {
        return $this->create($user);
    }
}

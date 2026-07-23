<?php

namespace App\Policies;

use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\Role;
use App\Models\User;

class MaintenancePolicy
{
    /**
     * UC-13: Technician, Scheduler/Manager, Admin can define calibration/PM schedules.
     */
    public function createSchedule(User $user): bool
    {
        return $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-13: Same roles can update a schedule.
     */
    public function updateSchedule(User $user, MaintenanceSchedule $schedule): bool
    {
        return $this->createSchedule($user);
    }

    /**
     * UC-14/UC-15: Technician, Scheduler/Manager, Admin can log calibration
     * and preventive maintenance events.
     */
    public function logEvent(User $user): bool
    {
        return $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-16: Same roles can log a repair event.
     */
    public function logRepair(User $user): bool
    {
        return $this->logEvent($user);
    }

    /**
     * UC-17: Technician, Scheduler/Manager, Admin can mark a device as
     * out for external calibration.
     */
    public function markOutForCalibration(User $user): bool
    {
        return $this->logEvent($user);
    }

    /**
     * Any authenticated user can view maintenance history.
     */
    public function view(User $user, MaintenanceEvent $event): bool
    {
        return true;
    }
}

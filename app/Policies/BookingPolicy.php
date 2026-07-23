<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;

class BookingPolicy
{
    /**
     * UC-21: Engineer, Technician, Scheduler/Manager, Admin can create bookings.
     * Auditors cannot book.
     */
    public function create(User $user): bool
    {
        return $user->canActAs(Role::ENGINEER)
            || $user->canActAs(Role::TECHNICIAN)
            || $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-25: Same roles can edit a booking's time range.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $this->create($user);
    }

    /**
     * UC-25: Same roles can cancel a booking.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $this->create($user);
    }

    /**
     * UC-23: Only Scheduler/Manager and Admin can override a booking conflict
     * or confirm a booking.
     */
    public function confirm(User $user, Booking $booking): bool
    {
        return $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * UC-23: Only Scheduler/Manager and Admin can override a conflict.
     */
    public function overrideConflict(User $user, Booking $booking): bool
    {
        return $user->canActAs(Role::SCHEDULER_MANAGER)
            || $user->canActAs(Role::ADMIN);
    }

    /**
     * Any authenticated user can view bookings and the availability calendar.
     */
    public function view(User $user, Booking $booking): bool
    {
        return true;
    }
}

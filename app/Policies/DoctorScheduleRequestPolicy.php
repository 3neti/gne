<?php

namespace App\Policies;

use App\Models\DoctorScheduleRequest;
use App\Models\User;

class DoctorScheduleRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DoctorScheduleRequest $doctorScheduleRequest): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DoctorScheduleRequest $doctorScheduleRequest): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DoctorScheduleRequest $doctorScheduleRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DoctorScheduleRequest $doctorScheduleRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DoctorScheduleRequest $doctorScheduleRequest): bool
    {
        return false;
    }
}

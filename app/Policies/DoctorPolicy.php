<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\RosterPeriod;
use App\Models\User;

class RosterPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function view(User $user, RosterPeriod $rosterPeriod): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function update(User $user, RosterPeriod $rosterPeriod): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function transition(User $user, RosterPeriod $rosterPeriod): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function generate(User $user, RosterPeriod $rosterPeriod): bool
    {
        return (bool) $user->is_roster_administrator;
    }

    public function delete(User $user, RosterPeriod $rosterPeriod): bool
    {
        return false;
    }
}

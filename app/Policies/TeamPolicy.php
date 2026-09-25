<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function manageMembers(User $user, Team $team): bool
    {
        return $team->memberships()
            ->where('user_id', $user->id)
            ->get()
            ->contains(fn ($membership) => $membership->role->canManageMembers());
    }
}

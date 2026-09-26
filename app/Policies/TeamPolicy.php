<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class TeamPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Team $team): bool
    {
        return $team->memberships()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $team->memberships()
            ->where('user_id', $user->id)
            ->get()
            ->contains(fn ($membership) => $membership->role->canManageMembers());
    }

    public function removeMember(User $user, Team $team, TeamMember $membership): bool
    {
        return $this->manageMembers($user, $team)
            && $membership->role !== TeamRole::Owner;
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamMember;

class TeamObserver
{
    public function created(Team $team): void
    {
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $team->owner_id,
            'role' => TeamRole::Owner,
        ]);
    }
}
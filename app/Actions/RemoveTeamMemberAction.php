<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\TeamMember;

final readonly class RemoveTeamMemberAction
{
    public function __construct(
        private LogActivityAction $logActivity,
    ) {
    }

    public function __invoke(TeamMember $membership): void
    {
        $team = $membership->team;
        $user = $membership->user;
        $role = $membership->role;

        $membership->delete();

        ($this->logActivity)(ActivityEvent::MemberRevoked, $team, [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'role' => $role->value,
        ]);
    }
}

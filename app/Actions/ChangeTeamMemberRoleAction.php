<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\TeamMember;
use Illuminate\Validation\ValidationException;

final readonly class ChangeTeamMemberRoleAction
{
    public function __construct(
        private LogActivityAction $logActivity,
    ) {
    }

    public function __invoke(TeamMember $membership, TeamRole $role): TeamMember
    {
        if ($membership->role === TeamRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'The owner role cannot be changed.',
            ]);
        }

        $previous = $membership->role;

        $membership->update(['role' => $role]);
        $membership->refresh();

        ($this->logActivity)(ActivityEvent::MemberRoleChanged, $membership->team, [
            'user_id' => $membership->user_id,
            'email' => $membership->user->email,
            'from' => $previous->value,
            'to' => $role->value,
        ]);

        return $membership;
    }
}

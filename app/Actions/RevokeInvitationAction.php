<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\TeamInvitation;
use App\Services\TeamInvitationService;

final readonly class RevokeInvitationAction
{
    public function __construct(
        private TeamInvitationService $invitations,
        private LogActivityAction $logActivity,
    ) {
    }

    public function __invoke(TeamInvitation $invitation): void
    {
        $team = $invitation->team;

        $this->invitations->revoke($invitation);

        ($this->logActivity)(ActivityEvent::MemberRevoked, $team, [
            'invitation_id' => $invitation->getKey(),
            'email' => $invitation->email,
            'role' => $invitation->role->value,
        ]);
    }
}

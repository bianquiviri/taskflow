<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\YouWereInvited;
use App\Services\TeamInvitationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final readonly class InviteTeamMemberAction
{
    public function __construct(
        private TeamInvitationService $invitations,
        private LogActivityAction $logActivity,
    ) {
    }

    public function __invoke(
        Team $team,
        string $email,
        TeamRole $role = TeamRole::Member,
        ?Carbon $expiresAt = null,
    ): TeamInvitation {
        $email = mb_strtolower(trim($email));

        $this->guard($team, $email, $role);

        $issued = $this->invitations->issue($team, $email, $role, $expiresAt);

        Notification::route('mail', $email)->notify(new YouWereInvited($issued->invitation, $issued->token));

        ($this->logActivity)(ActivityEvent::MemberInvited, $team, [
            'email' => $email,
            'role' => $role->value,
        ]);

        return $issued->invitation;
    }

    private function guard(Team $team, string $email, TeamRole $role): void
    {
        if ($role === TeamRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'The owner role cannot be granted through an invitation.',
            ]);
        }

        if ($team->memberships()->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the team.',
            ]);
        }

        if ($team->invitations()->pending()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An invitation for this address is already pending.',
            ]);
        }
    }
}

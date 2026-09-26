<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\IssuedTeamInvitation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamInvitationService
{
    public function create(Team $team, ?string $email = null, ?Carbon $expiresAt = null): string
    {
        return $this->issue($team, $email, TeamRole::Member, $expiresAt)->token;
    }

    /**
     * Issue an invitation and return it together with its raw token.
     */
    public function issue(
        Team $team,
        ?string $email = null,
        TeamRole $role = TeamRole::Member,
        ?Carbon $expiresAt = null,
    ): IssuedTeamInvitation {
        $token = Str::random(40);

        $invitation = $team->invitations()->create([
            'email' => $email,
            'token_hash' => Hash::make($token),
            'role' => $role,
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);

        return new IssuedTeamInvitation($invitation, $token);
    }

    public function findValid(string $token): ?TeamInvitation
    {
        $invitation = $this->find($token);

        return $invitation?->isPending() === true ? $invitation : null;
    }

    /**
     * Resolve a token whatever the state of the invitation it belongs to.
     */
    public function find(string $token): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->latest('id')
            ->get()
            ->first(
                fn (TeamInvitation $invitation) => Hash::check($token, $invitation->token_hash),
            );
    }

    public function revoke(TeamInvitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
    }
}

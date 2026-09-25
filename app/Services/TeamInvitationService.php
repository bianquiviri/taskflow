<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamInvitationService
{
    public function create(Team $team, ?string $email = null, ?Carbon $expiresAt = null): string
    {
        $token = Str::random(40);

        $team->invitations()->create([
            'email' => $email,
            'token_hash' => Hash::make($token),
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);

        return $token;
    }

    public function findValid(string $token): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
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

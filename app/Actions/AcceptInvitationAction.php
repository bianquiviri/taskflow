<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AcceptInvitationAction
{
    public function __construct(
        private TeamInvitationService $invitations,
        private LogActivityAction $logActivity,
    ) {
    }

    /**
     * Turn a one-time token into a team membership for the invited account.
     *
     * The seat is only created once: accepting an invitation that targets an
     * existing member consumes the token and leaves the membership untouched.
     */
    public function __invoke(string $token, User $user): TeamMember
    {
        $invitation = $this->invitations->find($token)
            ?? throw (new ModelNotFoundException())->setModel(TeamInvitation::class, [$token]);

        $this->guard($invitation, $user);

        $team = $invitation->team;

        $membership = $team->memberships()->where('user_id', $user->getKey())->first();

        if ($membership instanceof TeamMember) {
            $this->invitations->revoke($invitation);

            return $membership;
        }

        $membership = DB::transaction(function () use ($invitation, $team, $user): TeamMember {
            $membership = $team->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => $invitation->role,
            ]);

            $this->invitations->revoke($invitation);

            return $membership;
        });

        ($this->logActivity)(ActivityEvent::MemberAccepted, $team, [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'role' => $invitation->role->value,
        ]);

        return $membership;
    }

    private function guard(TeamInvitation $invitation, User $user): void
    {
        if ($invitation->isRevoked()) {
            throw ValidationException::withMessages(['token' => 'This invitation has been revoked.']);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => 'This invitation has expired.']);
        }

        if (! $invitation->isAddressedTo($user->email)) {
            throw ValidationException::withMessages(['token' => 'This invitation was sent to another email address.']);
        }
    }
}

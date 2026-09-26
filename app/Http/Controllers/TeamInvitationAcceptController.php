<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptInvitationAction;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationAcceptController extends Controller
{
    public function __construct(
        private readonly TeamInvitationService $invitations,
        private readonly AcceptInvitationAction $accept,
    ) {
    }

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->invitations->find($token) ?? abort(404);

        return Inertia::render('Teams/Invitation', [
            'team' => [
                'id' => $invitation->team_id,
                'name' => $invitation->team->name,
            ],
            'email' => $invitation->email,
            'role' => $invitation->role->describe(),
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'acceptUrl' => route('team-invitations.accept.store', $token),
            'status' => $this->status($invitation, $request->user()),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $membership = ($this->accept)($token, $request->user());

        return redirect()->route('teams.show', $membership->team)->with(
            $membership->wasRecentlyCreated ? 'success' : 'info',
            $membership->wasRecentlyCreated
                ? sprintf('You joined %s.', $membership->team->name)
                : sprintf('You are already a member of %s.', $membership->team->name),
        );
    }

    private function status(TeamInvitation $invitation, User $user): string
    {
        return match (true) {
            $invitation->isRevoked() => 'revoked',
            $invitation->isExpired() => 'expired',
            ! $invitation->isAddressedTo($user->email) => 'email_mismatch',
            $invitation->team->memberships()->where('user_id', $user->getKey())->exists() => 'already_member',
            default => 'valid',
        };
    }
}

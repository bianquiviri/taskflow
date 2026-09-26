<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function show(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        return Inertia::render('Teams/Show', [
            'team' => [
                'id' => $team->getKey(),
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'members' => $team->memberships()
                ->with('user:id,name,email')
                ->orderBy('id')
                ->get()
                ->map(fn (TeamMember $membership): array => [
                    'id' => $membership->getKey(),
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                    'canBeRemoved' => $request->user()->can('removeMember', [$team, $membership]),
                ])
                ->all(),
            'pendingInvitations' => $team->invitations()
                ->pending()
                ->orderBy('id')
                ->get()
                ->map(fn (TeamInvitation $invitation): array => [
                    'id' => $invitation->getKey(),
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expiresAt' => $invitation->expires_at->toIso8601String(),
                ])
                ->all(),
            'roles' => TeamRole::options(),
            'invitableRoles' => TeamRole::invitable(),
            'canManageMembers' => $request->user()->can('manageMembers', $team),
        ]);
    }
}

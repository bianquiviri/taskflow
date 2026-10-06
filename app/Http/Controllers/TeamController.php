<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTeamAction;
use App\Enums\TeamRole;
use App\Http\Requests\StoreTeamRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private readonly CreateTeamAction $createTeam,
    ) {
    }

    public function create(): Response
    {
        $this->authorize('create', Team::class);

        return Inertia::render('Teams/Create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        $team = ($this->createTeam)($request->user(), $request->validated());

        return redirect()->route('teams.show', $team)->with('success', 'Team created.');
    }

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

    public function settings(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        return Inertia::render('Teams/Settings', [
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
                    'canBeReassigned' => $request->user()->can('updateMemberRole', [$team, $membership]),
                ])
                ->all(),
            'invitations' => $team->invitations()
                ->orderBy('id')
                ->get()
                ->map(fn (TeamInvitation $invitation): array => [
                    'id' => $invitation->getKey(),
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'status' => $invitation->status(),
                    'expiresAt' => $invitation->expires_at->toIso8601String(),
                    'canBeResent' => $request->user()->can('resendInvitation', [$team, $invitation]),
                    'canBeCancelled' => $request->user()->can('manageMembers', $team),
                ])
                ->all(),
            'roles' => TeamRole::options(),
            'assignableRoles' => TeamRole::invitable(),
            'canManageMembers' => $request->user()->can('manageMembers', $team),
        ]);
    }
}

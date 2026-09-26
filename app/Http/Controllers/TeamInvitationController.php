<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InviteTeamMemberAction;
use App\Actions\RevokeInvitationAction;
use App\Enums\TeamRole;
use App\Http\Requests\StoreTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamInvitationController extends Controller
{
    public function __construct(
        private readonly InviteTeamMemberAction $invite,
        private readonly RevokeInvitationAction $revoke,
    ) {
    }

    public function store(StoreTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        ($this->invite)(
            $team,
            $request->validated('email'),
            TeamRole::from($request->validated('role', TeamRole::Member->value)),
        );

        return redirect()->route('teams.show', $team)->with('success', 'Invitation sent.');
    }

    public function destroy(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        ($this->revoke)($invitation);

        return redirect()->route('teams.show', $team)->with('success', 'Invitation revoked.');
    }
}

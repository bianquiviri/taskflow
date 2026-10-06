<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ChangeTeamMemberRoleAction;
use App\Actions\RemoveTeamMemberAction;
use App\Enums\TeamRole;
use App\Http\Requests\UpdateTeamMemberRoleRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Support\TeamRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function __construct(
        private readonly RemoveTeamMemberAction $removeMember,
        private readonly ChangeTeamMemberRoleAction $changeRole,
        private readonly TeamRedirector $redirector,
    ) {
    }

    public function destroy(Request $request, Team $team, TeamMember $membership): RedirectResponse
    {
        $this->authorize('removeMember', [$team, $membership]);

        ($this->removeMember)($membership);

        return $this->redirector->to($team, $request)
            ->with('success', 'Member removed from the team.');
    }

    public function update(UpdateTeamMemberRoleRequest $request, Team $team, TeamMember $membership): RedirectResponse
    {
        $this->authorize('updateMemberRole', [$team, $membership]);

        ($this->changeRole)($membership, TeamRole::from($request->validated('role')));

        return $this->redirector->to($team, $request)
            ->with('success', 'Member role updated.');
    }
}

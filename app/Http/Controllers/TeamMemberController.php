<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RemoveTeamMemberAction;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function __construct(
        private readonly RemoveTeamMemberAction $removeMember,
    ) {
    }

    public function destroy(Request $request, Team $team, TeamMember $membership): RedirectResponse
    {
        $this->authorize('removeMember', [$team, $membership]);

        ($this->removeMember)($membership);

        return redirect()->route('teams.show', $team)->with('success', 'Member removed from the team.');
    }
}

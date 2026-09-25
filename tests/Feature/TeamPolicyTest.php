<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function addTeamMember(Team $team, User $user, TeamRole $role = TeamRole::Member): TeamMember
{
    return TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => $role]);
}

test('team owner can manage its members', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    addTeamMember($team, $member, TeamRole::Admin);

    expect(Gate::forUser($team->owner)->allows('manageMembers', $team))->toBeTrue();
});

test('team admin can manage its members', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    addTeamMember($team, $admin, TeamRole::Admin);

    expect(Gate::forUser($admin)->allows('manageMembers', $team))->toBeTrue();
});

test('plain team member cannot manage its members', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    addTeamMember($team, $member);

    expect(Gate::forUser($member)->denies('manageMembers', $team))->toBeTrue();
});

test('a user outside the team cannot manage its members', function () {
    $team = Team::factory()->create();
    $outsider = User::factory()->create();

    expect(Gate::forUser($outsider)->denies('manageMembers', $team))->toBeTrue();
});

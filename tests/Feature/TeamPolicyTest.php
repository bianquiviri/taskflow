<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
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

test('a manager may reassign a member role but never the owner seat', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    addTeamMember($team, $admin, TeamRole::Admin);
    $membership = addTeamMember($team, User::factory()->create());
    $ownerMembership = $team->memberships()->where('user_id', $team->owner_id)->firstOrFail();

    $gate = Gate::forUser($admin);

    expect($gate->allows('updateMemberRole', [$team, $membership]))->toBeTrue()
        ->and($gate->denies('updateMemberRole', [$team, $ownerMembership]))->toBeTrue();
});

test('a plain member cannot reassign any role', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $membership = addTeamMember($team, $member);
    $other = addTeamMember($team, User::factory()->create());

    expect(Gate::forUser($member)->denies('updateMemberRole', [$team, $other]))->toBeTrue();
});

test('a manager may only resend a pending invitation', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    addTeamMember($team, $admin, TeamRole::Admin);
    $pending = TeamInvitation::factory()->create(['team_id' => $team->id]);
    $expired = TeamInvitation::factory()->create(['team_id' => $team->id, 'expires_at' => now()->subDay()]);
    $revoked = TeamInvitation::factory()->create(['team_id' => $team->id, 'revoked_at' => now()]);

    $gate = Gate::forUser($admin);

    expect($gate->allows('resendInvitation', [$team, $pending]))->toBeTrue()
        ->and($gate->denies('resendInvitation', [$team, $expired]))->toBeTrue()
        ->and($gate->denies('resendInvitation', [$team, $revoked]))->toBeTrue();
});

test('a plain member cannot resend an invitation', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    addTeamMember($team, $member);
    $pending = TeamInvitation::factory()->create(['team_id' => $team->id]);

    expect(Gate::forUser($member)->denies('resendInvitation', [$team, $pending]))->toBeTrue();
});

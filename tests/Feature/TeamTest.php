<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

test('creating a team adds the owner as a member with the owner role', function () {
    $owner = User::factory()->create();

    $team = Team::factory()->forOwner($owner)->create();

    expect($team->owner->is($owner))->toBeTrue()
        ->and($team->memberships)->toHaveCount(1)
        ->and($team->memberships->first()->user->is($owner))->toBeTrue()
        ->and($team->memberships->first()->role)->toBe(TeamRole::Owner);
});

test('a user can join a team as a member', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $membership = TeamMember::factory()->forTeam($team)->forUser($user)->create();

    expect($membership->team->is($team))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($membership->role)->toBe(TeamRole::Member)
        ->and($team->memberships)->toHaveCount(2);
});

test('a team member role is stored as a TeamRole enum', function () {
    $membership = TeamMember::factory()->create(['role' => TeamRole::Admin]);

    expect($membership->role)->toBeInstanceOf(TeamRole::class)
        ->and($membership->role)->toBe(TeamRole::Admin);
});

test('a team member role can be promoted or demoted', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    TeamMember::factory()->forTeam($team)->forUser($user)->create(['role' => TeamRole::Member]);
    $membership = TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->firstOrFail();

    $membership->update(['role' => TeamRole::Admin]);

    expect($membership->fresh()->role)->toBe(TeamRole::Admin);
});

test('a team exposes its members through the members relation', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);

    expect($team->members)->toHaveCount(2)
        ->and($team->members->pluck('id'))->toContain($user->id);
});
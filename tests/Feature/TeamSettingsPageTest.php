<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;

it('renders the team settings page for the owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Platform']);
    $member = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Admin,
    ]);
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
    ]);

    $this->actingAs($owner)
        ->get(route('teams.settings', $team))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Teams/Settings')
                ->where('team.name', 'Platform')
                ->where('canManageMembers', true)
                ->where('roles', TeamRole::options())
                ->where('assignableRoles', TeamRole::invitable())
                ->where('members.0.role', TeamRole::Owner->value)
                ->where('members.0.canBeRemoved', false)
                ->where('members.0.canBeReassigned', false)
                ->where('members.1.name', 'Grace Hopper')
                ->where('members.1.role', TeamRole::Admin->value)
                ->where('members.1.canBeRemoved', true)
                ->where('members.1.canBeReassigned', true)
                ->where('invitations.0.email', 'invitee@example.com')
                ->where('invitations.0.status', 'pending')
                ->where('invitations.0.canBeResent', true)
                ->where('invitations.0.canBeCancelled', true),
        );

    expect($invitation->team_id)->toBe($team->id);
});

it('lets an admin open the settings page and manage members', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $admin->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($admin)
        ->get(route('teams.settings', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManageMembers', true));
});

it('lets a plain member open the settings page without managing it', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Member,
    ]);

    $this->actingAs($member)
        ->get(route('teams.settings', $team))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('canManageMembers', false)
                ->where('members.1.canBeRemoved', false)
                ->where('members.1.canBeReassigned', false),
        );
});

it('shows the pending invitations and their status', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $pending = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'pending@example.com',
    ]);
    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'expired@example.com',
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->get(route('teams.settings', $team))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('invitations.0.email', 'pending@example.com')
                ->where('invitations.0.status', 'pending')
                ->where('invitations.1.email', 'expired@example.com')
                ->where('invitations.1.status', 'expired')
                ->where('invitations.1.canBeResent', false)
                ->where('invitations.1.canBeCancelled', true),
        );

    expect($pending->isPending())->toBeTrue();
});

it('forbids a guest and an outsider from opening the settings page', function () {
    $team = Team::factory()->create();
    $outsider = User::factory()->create();

    $this->get(route('teams.settings', $team))->assertRedirect(route('login'));

    $this->actingAs($outsider)
        ->get(route('teams.settings', $team))
        ->assertForbidden();
});

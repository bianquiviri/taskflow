<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;

it('revokes a pending invitation with an audit event', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
    ]);

    $this->actingAs($owner)
        ->delete(route('teams.invitations.destroy', [$team, $invitation]))
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHas('success');

    expect($invitation->fresh()->revoked_at)->not->toBeNull();

    $log = ActivityLog::forSubject($team)->sole();

    expect($log->event)->toBe(ActivityEvent::MemberRevoked)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->meta)->toBe([
            'invitation_id' => $invitation->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);
});

it('removes a team member with an audit event', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create(['email' => 'member@example.com']);
    $membership = TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $membership]))
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('team_members', ['id' => $membership->id]);

    $log = ActivityLog::forSubject($team)->sole();

    expect($log->event)->toBe(ActivityEvent::MemberRevoked)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->meta)->toBe([
            'user_id' => $member->id,
            'email' => 'member@example.com',
            'role' => 'admin',
        ]);
});

it('lets an admin remove a member', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $membership = TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $admin->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $membership]))
        ->assertRedirect(route('teams.show', $team));
});

it('forbids a plain member from revoking invitations and members', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    $membership = TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Member,
    ]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

    $this->actingAs($member)
        ->delete(route('teams.invitations.destroy', [$team, $invitation]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('teams.members.destroy', [$team, $membership]))
        ->assertForbidden();

    expect($invitation->fresh()->revoked_at)->toBeNull()
        ->and(TeamMember::whereKey($membership->id)->exists())->toBeTrue();
});

it('never removes the team owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = $team->memberships()->where('user_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $membership]))
        ->assertForbidden();

    $this->assertDatabaseHas('team_members', ['id' => $membership->id]);
});

it('tells the team page which members the viewer may remove', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    TeamMember::factory()->create(['team_id' => $team->id]);

    $this->actingAs($owner)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('members.0.role', TeamRole::Owner->value)
            ->where('members.0.canBeRemoved', false)
            ->where('members.1.role', TeamRole::Member->value)
            ->where('members.1.canBeRemoved', true),
        );
});

it('does not touch invitations or members of another team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $otherTeam = Team::factory()->create(['owner_id' => $owner->id]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $otherTeam->id]);
    $membership = TeamMember::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($owner)
        ->delete(route('teams.invitations.destroy', [$team, $invitation]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $membership]))
        ->assertNotFound();

    expect($invitation->fresh()->revoked_at)->toBeNull()
        ->and(TeamMember::whereKey($membership->id)->exists())->toBeTrue();
});

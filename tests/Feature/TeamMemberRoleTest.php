<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

it('lets the owner change a member role with an audit event', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create(['email' => 'grace@example.com']);
    $membership = TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Member,
    ]);

    $this->actingAs($owner)
        ->from(route('teams.settings', $team))
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Admin->value])
        ->assertRedirect(route('teams.settings', $team))
        ->assertSessionHas('success');

    expect($membership->fresh()->role)->toBe(TeamRole::Admin);

    $log = ActivityLog::forSubject($team)->sole();

    expect($log->event)->toBe(ActivityEvent::MemberRoleChanged)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->meta)->toBe([
            'user_id' => $member->id,
            'email' => 'grace@example.com',
            'from' => 'member',
            'to' => 'admin',
        ]);
});

it('lets an admin change a member role', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $admin->id, 'role' => TeamRole::Admin]);
    $membership = TeamMember::factory()->create([
        'team_id' => $team->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Member->value])
        ->assertRedirect();

    expect($membership->fresh()->role)->toBe(TeamRole::Member);
});

it('keeps the owner seat out of reach', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = $team->memberships()->where('user_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Member->value])
        ->assertForbidden();

    expect($membership->fresh()->role)->toBe(TeamRole::Owner);
});

it('never grants the owner role through a role change', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Admin]);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Owner->value])
        ->assertSessionHasErrors('role');

    expect($membership->fresh()->role)->toBe(TeamRole::Admin);
});

it('rejects an unknown role', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Member]);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => 'superuser'])
        ->assertSessionHasErrors('role');

    expect($membership->fresh()->role)->toBe(TeamRole::Member);
});

it('requires a role', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Member]);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $membership]), [])
        ->assertSessionHasErrors('role');

    expect($membership->fresh()->role)->toBe(TeamRole::Member);
});

it('forbids a plain member from changing roles', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => TeamRole::Member]);
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Admin]);

    $this->actingAs($member)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Member->value])
        ->assertForbidden();

    expect($membership->fresh()->role)->toBe(TeamRole::Admin);
});

it('forbids an outsider from changing roles', function () {
    $team = Team::factory()->create();
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Admin]);

    $this->actingAs(User::factory()->create())
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Member->value])
        ->assertForbidden();

    expect($membership->fresh()->role)->toBe(TeamRole::Admin);
});

it('does not change the role of a member of another team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $otherTeam = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = TeamMember::factory()->create(['team_id' => $otherTeam->id, 'role' => TeamRole::Admin]);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Member->value])
        ->assertNotFound();

    expect($membership->fresh()->role)->toBe(TeamRole::Admin);
});

it('sends the team member management back to the page it came from', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Member]);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $membership]), ['role' => TeamRole::Admin->value])
        ->assertRedirect(route('teams.show', $team));
});

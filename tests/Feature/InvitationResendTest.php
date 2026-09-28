<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\YouWereInvited;
use Illuminate\Support\Facades\Notification;

it('resends a pending invitation with a fresh token and an audit event', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
        'expires_at' => now()->addDay(),
    ]);
    $originalTokenHash = $invitation->token_hash;

    $this->actingAs($owner)
        ->from(route('teams.settings', $team))
        ->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertRedirect(route('teams.settings', $team))
        ->assertSessionHas('success');

    $invitation->refresh();

    expect($invitation->token_hash)->not->toBe($originalTokenHash)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->revoked_at)->toBeNull();

    Notification::assertSentOnDemand(YouWereInvited::class, function (YouWereInvited $notification) use ($invitation): bool {
        return $notification->invitation->is($invitation)
            && $notification->invitation->email === 'invitee@example.com';
    });

    $log = ActivityLog::forSubject($team)->sole();

    expect($log->event)->toBe(ActivityEvent::InvitationResent)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->meta)->toBe([
            'invitation_id' => $invitation->id,
            'email' => 'invitee@example.com',
            'role' => $invitation->role->value,
        ]);
});

it('lets an admin resend an invitation', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $admin->id, 'role' => TeamRole::Admin]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

    $this->actingAs($admin)
        ->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertRedirect();

    Notification::assertSentOnDemand(YouWereInvited::class);
});

it('refuses to resend an expired or revoked invitation', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $expired = TeamInvitation::factory()->create(['team_id' => $team->id, 'expires_at' => now()->subDay()]);
    $revoked = TeamInvitation::factory()->create(['team_id' => $team->id, 'revoked_at' => now()]);

    $this->actingAs($owner)
        ->post(route('teams.invitations.resend', [$team, $expired]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('teams.invitations.resend', [$team, $revoked]))
        ->assertForbidden();

    Notification::assertNothingSent();
});

it('forbids a plain member from resending an invitation', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => TeamRole::Member]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);
    $tokenHash = $invitation->token_hash;

    $this->actingAs($member)
        ->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertForbidden();

    expect($invitation->fresh()->token_hash)->toBe($tokenHash);
});

it('forbids an outsider from resending an invitation', function () {
    $team = Team::factory()->create();
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertForbidden();
});

it('does not resend an invitation belonging to another team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $otherTeam = Team::factory()->create(['owner_id' => $owner->id]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $otherTeam->id]);
    $tokenHash = $invitation->token_hash;

    $this->actingAs($owner)
        ->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertNotFound();

    expect($invitation->fresh()->token_hash)->toBe($tokenHash);
});

it('keeps the settings page as the destination of the reused team routes', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $membership = TeamMember::factory()->create(['team_id' => $team->id, 'role' => TeamRole::Member]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

    $this->actingAs($owner)
        ->from(route('teams.settings', $team))
        ->post(route('teams.invitations.store', $team), ['email' => 'new@example.com', 'role' => 'member'])
        ->assertRedirect(route('teams.settings', $team));

    $this->actingAs($owner)
        ->from(route('teams.settings', $team))
        ->delete(route('teams.invitations.destroy', [$team, $invitation]))
        ->assertRedirect(route('teams.settings', $team));

    $this->actingAs($owner)
        ->from(route('teams.settings', $team))
        ->delete(route('teams.members.destroy', [$team, $membership]))
        ->assertRedirect(route('teams.settings', $team));
});

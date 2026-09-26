<?php

declare(strict_types=1);

use App\Actions\AcceptInvitationAction;
use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

it('adds the invitee to the team with the invited role', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', TeamRole::Admin)->token;

    $membership = app(AcceptInvitationAction::class)($token, $invitee);

    expect($membership->team->is($team))->toBeTrue()
        ->and($membership->user->is($invitee))->toBeTrue()
        ->and($membership->role)->toBe(TeamRole::Admin);
});

it('consumes the one-time token once accepted', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com')->token;
    $action = app(AcceptInvitationAction::class);

    $action($token, $invitee);

    expect(TeamInvitation::sole()->revoked_at)->not->toBeNull();

    expect(fn () => $action($token, $invitee))->toThrow(ValidationException::class);
});

it('records the acceptance on the team audit trail', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', TeamRole::Admin)->token;

    $this->actingAs($invitee);
    app(AcceptInvitationAction::class)($token, $invitee);

    $log = ActivityLog::forSubject($team)->sole();

    expect($log->event)->toBe(ActivityEvent::MemberAccepted)
        ->and($log->actor_id)->toBe($invitee->id)
        ->and($log->subject_type)->toBe('team')
        ->and($log->meta)->toBe([
            'user_id' => $invitee->id,
            'email' => 'invitee@example.com',
            'role' => 'admin',
        ]);
});

it('rejects an expired invitation', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', expiresAt: now()->subDay())->token;

    expect(fn () => app(AcceptInvitationAction::class)($token, $invitee))
        ->toThrow(ValidationException::class);

    expect($team->memberships()->count())->toBe(1);
});

it('rejects a revoked invitation', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $issued = app(TeamInvitationService::class)->issue($team, 'invitee@example.com');
    app(TeamInvitationService::class)->revoke($issued->invitation);

    expect(fn () => app(AcceptInvitationAction::class)($issued->token, $invitee))
        ->toThrow(ValidationException::class);

    expect($team->memberships()->count())->toBe(1);
});

it('rejects an unknown token', function () {
    $invitee = User::factory()->create();

    expect(fn () => app(AcceptInvitationAction::class)('not-a-real-token', $invitee))
        ->toThrow(ModelNotFoundException::class);
});

it('rejects an acceptance from another email address', function () {
    $team = Team::factory()->create();
    $impostor = User::factory()->create(['email' => 'impostor@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com')->token;

    expect(fn () => app(AcceptInvitationAction::class)($token, $impostor))
        ->toThrow(ValidationException::class);

    expect($team->memberships()->count())->toBe(1);
});

it('does not duplicate the seat of an existing member', function () {
    $team = Team::factory()->create();
    $owner = $team->owner;
    $token = app(TeamInvitationService::class)->issue($team, $owner->email, TeamRole::Admin)->token;

    $membership = app(AcceptInvitationAction::class)($token, $owner);

    expect($membership->role)->toBe(TeamRole::Owner)
        ->and($team->memberships()->count())->toBe(1)
        ->and(TeamInvitation::sole()->revoked_at)->not->toBeNull();
});

it('lets the invitee accept through the browser', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com')->token;

    $this->actingAs($invitee)
        ->post(route('team-invitations.accept.store', $token))
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $invitee->id,
        'role' => TeamRole::Member->value,
    ]);
});

it('tells an existing member that the seat is already theirs', function () {
    $team = Team::factory()->create();
    $owner = $team->owner;
    $token = app(TeamInvitationService::class)->issue($team, $owner->email)->token;

    $this->actingAs($owner)
        ->post(route('team-invitations.accept.store', $token))
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHas('info', 'You are already a member of '.$team->name.'.');
});

it('keeps an expired invitation from adding a member over http', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', expiresAt: now()->subHour())->token;

    $this->actingAs($invitee)
        ->post(route('team-invitations.accept.store', $token))
        ->assertSessionHasErrors('token');

    $this->assertDatabaseMissing('team_members', [
        'team_id' => $team->id,
        'user_id' => $invitee->id,
    ]);
});

it('answers 404 on an unknown invitation link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('team-invitations.accept', 'not-a-real-token'))
        ->assertNotFound();
});

it('shows the invitation details before accepting', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', TeamRole::Admin)->token;

    $this->actingAs($invitee)
        ->get(route('team-invitations.accept', $token))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Teams/Invitation')
            ->where('team.name', $team->name)
            ->where('email', 'invitee@example.com')
            ->where('role.value', 'admin')
            ->where('role.label', 'Admin')
            ->where('status', 'valid'),
        );
});

it('flags an expired invitation on the accept page', function () {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', expiresAt: now()->subDay())->token;

    $this->actingAs($invitee)
        ->get(route('team-invitations.accept', $token))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Teams/Invitation')
            ->where('status', 'expired'),
        );
});

it('flags an invitation addressed to somebody else', function () {
    $team = Team::factory()->create();
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com')->token;

    $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
        ->get(route('team-invitations.accept', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('status', 'email_mismatch'));
});

it('flags an invitation the signed-in account already answered', function () {
    $team = Team::factory()->create();
    $owner = $team->owner;
    $token = app(TeamInvitationService::class)->issue($team, $owner->email)->token;

    $this->actingAs($owner)
        ->get(route('team-invitations.accept', $token))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Teams/Invitation')
            ->where('status', 'already_member')
            ->where('acceptUrl', route('team-invitations.accept.store', $token)),
        );
});

it('requires an authenticated account to accept', function () {
    $team = Team::factory()->create();
    $token = app(TeamInvitationService::class)->issue($team, 'invitee@example.com')->token;

    $this->get(route('team-invitations.accept', $token))->assertRedirect(route('login'));
});

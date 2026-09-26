<?php

declare(strict_types=1);

use App\Actions\InviteTeamMemberAction;
use App\Enums\ActivityEvent;
use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\YouWereInvited;
use App\Services\TeamInvitationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('creates a pending invitation that expires in seven days', function () {
    Notification::fake();
    $team = Team::factory()->create();

    $invitation = app(InviteTeamMemberAction::class)($team, 'invitee@example.com');

    expect($invitation)->toBeInstanceOf(TeamInvitation::class)
        ->and($invitation->team->is($team))->toBeTrue()
        ->and($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->role)->toBe(TeamRole::Member)
        ->and($invitation->revoked_at)->toBeNull()
        ->and($invitation->isPending())->toBeTrue()
        ->and($invitation->expires_at->diffInDays(now(), true))->toEqualWithDelta(7, 0.1);
});

it('grants the invited role to the invitation', function () {
    Notification::fake();
    $team = Team::factory()->create();

    $invitation = app(InviteTeamMemberAction::class)($team, 'lead@example.com', TeamRole::Admin);

    expect($invitation->role)->toBe(TeamRole::Admin);
});

it('stores only the hash of the one-time token', function () {
    $team = Team::factory()->create();

    $issued = app(TeamInvitationService::class)->issue($team, 'invitee@example.com');

    expect($issued->invitation->token_hash)->not->toBe($issued->token)
        ->and(Hash::check($issued->token, $issued->invitation->token_hash))->toBeTrue()
        ->and(app(TeamInvitationService::class)->find($issued->token)?->is($issued->invitation))->toBeTrue();
});

it('queues the invitation email on the default queue', function () {
    Queue::fake();
    $team = Team::factory()->create();

    app(InviteTeamMemberAction::class)($team, 'invitee@example.com');

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof YouWereInvited
            && $job->channels === ['mail']
            && $job->queue === null,
    );
});

it('sends the invitation email to the invited address only', function () {
    Notification::fake();
    $team = Team::factory()->create();

    app(InviteTeamMemberAction::class)($team, 'invitee@example.com');

    Notification::assertSentTo(
        new AnonymousNotifiable(),
        fn (YouWereInvited $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'invitee@example.com'
            && $notification->invitation->team->is($team)
            && $channels === ['mail'],
    );
});

it('delivers the invitation email asynchronously', function () {
    $issued = app(TeamInvitationService::class)->issue(Team::factory()->create(), 'invitee@example.com');

    expect(new YouWereInvited($issued->invitation, $issued->token))->toBeInstanceOf(ShouldQueue::class);
});

it('builds an accept link from the one-time token', function () {
    $team = Team::factory()->create();
    $issued = app(TeamInvitationService::class)->issue($team, 'invitee@example.com', TeamRole::Admin);

    $mail = (new YouWereInvited($issued->invitation, $issued->token))->toMail(new AnonymousNotifiable());

    expect($mail->actionUrl)->toBe(route('team-invitations.accept', $issued->token))
        ->and($mail->actionText)->toBe('Accept invitation')
        ->and($mail->subject)->toContain($team->name)
        ->and($mail->introLines[0])->toContain('as a Admin');
});

it('lets a team owner invite somebody with a role', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'new@example.com',
            'role' => TeamRole::Admin->value,
        ])
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHas('success');

    $invitation = TeamInvitation::sole();

    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->role)->toBe(TeamRole::Admin)
        ->and($invitation->team_id)->toBe($team->id);
});

it('records the invitation on the team audit trail', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)->post(route('teams.invitations.store', $team), [
        'email' => 'new@example.com',
        'role' => TeamRole::Member->value,
    ]);

    $log = ActivityLog::forSubject($team)->sole();

    expect($log->event)->toBe(ActivityEvent::MemberInvited)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->subject_type)->toBe('team')
        ->and($log->subject_id)->toBe($team->id)
        ->and($log->meta)->toBe(['email' => 'new@example.com', 'role' => 'member']);
});

it('validates the invited email and role', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'not-an-email', 'role' => TeamRole::Owner->value])
        ->assertSessionHasErrors(['email', 'role']);

    expect(TeamInvitation::count())->toBe(0);
});

it('refuses to invite an existing member', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create(['email' => 'member@example.com']);
    $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'Member@Example.com'])
        ->assertSessionHasErrors('email');

    expect(TeamInvitation::count())->toBe(0);
});

it('refuses a second pending invitation for the same address', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'invitee@example.com'])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'invitee@example.com'])
        ->assertSessionHasErrors('email');

    expect(TeamInvitation::count())->toBe(1);
});

it('forbids a plain member from inviting', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);

    $this->actingAs($member)
        ->post(route('teams.invitations.store', $team), ['email' => 'invitee@example.com'])
        ->assertForbidden();

    expect(TeamInvitation::count())->toBe(0);
});

it('sends a guest to the login page before inviting', function () {
    $team = Team::factory()->create();

    $this->post(route('teams.invitations.store', $team), ['email' => 'invitee@example.com'])
        ->assertRedirect(route('login'));
});

it('lists the pending invitations on the team page', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $invited = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'pending@example.com']);
    TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'revoked@example.com', 'revoked_at' => now()]);
    TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'expired@example.com', 'expires_at' => now()->subDay()]);
    TeamInvitation::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($owner)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Teams/Show')
            ->where('team.id', $team->id)
            ->where('canManageMembers', true)
            ->has('members', 1)
            ->where('members.0.email', $owner->email)
            ->where('members.0.canBeRemoved', false)
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.id', $invited->id)
            ->where('pendingInvitations.0.email', 'pending@example.com')
            ->where('pendingInvitations.0.role', 'member')
            ->has('roles', 3),
        );
});

it('shows the team page to a plain member without the invite form', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);

    $this->actingAs($member)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Teams/Show')
            ->where('canManageMembers', false)
            ->has('members', 2),
        );
});

it('hides the team page from outsiders', function () {
    $team = Team::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('teams.show', $team))
        ->assertForbidden();
});

it('sends a guest to the login page before opening a team', function () {
    $team = Team::factory()->create();

    $this->get(route('teams.show', $team))->assertRedirect(route('login'));
});

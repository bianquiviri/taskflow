<?php

declare(strict_types=1);

use App\Models\Team;
use App\Services\TeamInvitationService;

test('an invitation can be created for a team with a token and expiry', function () {
    $team = Team::factory()->create();
    $service = new TeamInvitationService();

    $token = $service->create($team, 'invitee@example.com');

    $invitation = $team->invitations()->firstOrFail();

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->revoked_at)->toBeNull();
});

test('a valid token resolves to its invitation', function () {
    $team = Team::factory()->create();
    $service = new TeamInvitationService();

    $token = $service->create($team, 'invitee@example.com');
    $invitation = $service->findValid($token);

    expect($invitation)->not->toBeNull()
        ->and($invitation->team->is($team))->toBeTrue();
});

test('an invitation can be created with a custom expiry', function () {
    $team = Team::factory()->create();
    $service = new TeamInvitationService();

    $token = $service->create($team, 'invitee@example.com', now()->addHours(2));
    $invitation = $service->findValid($token);

    expect($invitation->expires_at->diffInHours(now(), true))->toEqualWithDelta(2, 0.1)
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

test('an unknown token resolves to nothing', function () {
    $team = Team::factory()->create();
    $service = new TeamInvitationService();

    expect($service->findValid('not-a-real-token'))->toBeNull();
});

test('an expired invitation is rejected', function () {
    $team = Team::factory()->create();
    $service = new TeamInvitationService();

    $token = $service->create($team, 'invitee@example.com', now()->subDay());

    expect($service->findValid($token))->toBeNull();
});

test('a revoked invitation is rejected', function () {
    $team = Team::factory()->create();
    $service = new TeamInvitationService();

    $token = $service->create($team, 'invitee@example.com');
    $invitation = $service->findValid($token);

    $service->revoke($invitation);

    expect($service->findValid($token))->toBeNull()
        ->and($invitation->fresh()->revoked_at)->not->toBeNull();
});

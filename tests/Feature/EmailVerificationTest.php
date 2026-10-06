<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmail as QueuedVerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('redirects guests to login from the first authenticated area', function () {
    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));
});

it('gates unverified users before the projects area', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('projects.index'))
        ->assertRedirect(route('verification.notice'));
});

it('allows verified users into the projects area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('projects.index'))
        ->assertOk();
});

it('renders the verification notice for an unverified user', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

it('redirects a verified user away from the verification notice', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('verification.notice'))
        ->assertRedirect(route('projects.index'));
});

it('verifies an email from a valid signed URL', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinute(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('projects.index'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('rejects an invalid verification signature', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.verify', [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]))
        ->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('queues a verification email when requested', function () {
    Queue::fake();

    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('verification.send'))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof QueuedVerifyEmail,
    );
});

it('throttles verification email resend requests', function () {
    Queue::fake();
    $user = User::factory()->unverified()->create();

    foreach (range(1, 7) as $attempt) {
        $response = $this->actingAs($user)->post(route('verification.send'));

        if ($attempt === 7) {
            $response->assertTooManyRequests();
        } else {
            $response->assertRedirect();
        }
    }

    Queue::assertPushed(SendQueuedNotifications::class, 6);
});

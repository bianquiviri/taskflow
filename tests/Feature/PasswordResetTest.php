<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPassword as QueuedResetPassword;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

it('renders the forgot password page for guests', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

it('queues a password reset link and returns to the form', function () {
    Queue::fake();
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status', 'password-reset-link-sent');

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof QueuedResetPassword,
    );
});

it('does not reveal whether an email exists', function () {
    Queue::fake();

    $this->post(route('password.email'), ['email' => 'missing@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status', 'password-reset-link-sent');

    Queue::assertNothingPushed();
});

it('validates the forgot password request', function () {
    $this->post(route('password.email'), ['email' => 'invalid'])
        ->assertSessionHasErrors('email');
});

it('renders the reset password page with its token and email', function () {
    $this->get(route('password.reset', [
        'token' => 'reset-token',
        'email' => 'ada@example.com',
    ]))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('email', 'ada@example.com')
            ->where('token', 'reset-token'),
    );
});

it('resets a password with a valid token and authenticates the user', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-correct-password',
        'password_confirmation' => 'new-correct-password',
    ])->assertRedirect(route('projects.index'));

    $this->assertAuthenticatedAs($user->refresh());
    expect(Hash::check('new-correct-password', $user->password))->toBeTrue();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

it('rejects an invalid password reset token', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-correct-password',
        'password_confirmation' => 'new-correct-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('validates the new password', function () {
    $this->post(route('password.update'), [
        'token' => 'reset-token',
        'email' => 'ada@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');
});

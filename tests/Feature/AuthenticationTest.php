<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmail as QueuedVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

it('renders the registration page for guests', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Register'));
});

it('registers an unverified user and queues verification mail', function () {
    Queue::fake();

    $response = $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertRedirect(route('projects.index'));

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertNull($user->email_verified_at);
    $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
    $this->assertNotSame('correct-horse-battery-staple', $user->password);

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof QueuedVerifyEmail,
    );
});

it('validates registration input', function () {
    $this->post(route('register'), [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
});

it('requires users to implement email verification', function () {
    expect(new User())->toBeInstanceOf(MustVerifyEmail::class);
});

it('renders the login page for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('authenticates a verified user with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'grace@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->post(route('login'), [
        'email' => 'grace@example.com',
        'password' => 'correct-password',
    ])->assertRedirect(route('projects.index'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->post(route('login'), [
        'email' => 'grace@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs a user out and invalidates the session', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('redirects authenticated users away from guest auth pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('projects.index'));
});

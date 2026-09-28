<?php

declare(strict_types=1);

use App\Enums\Theme;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('renders the profile page for the signed in user', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'theme' => Theme::Dark,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Profile/Edit')
            ->where('user.name', 'Ada Lovelace')
            ->where('user.email', 'ada@example.com')
            ->where('user.theme', 'dark')
            ->where('user.avatar', null)
            ->where('user.emailVerified', true),
        );
});

it('renders the password page for the signed in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.password'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Profile/Password'));
});

it('updates the name', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ada King', 'email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Profile updated.');

    expect($user->fresh()->name)->toBe('Ada King')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

it('keeps the verified state when the email does not change', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ada@example.com']);
    $verifiedAt = $user->email_verified_at;

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ada@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email_verified_at->timestamp)->toBe($verifiedAt->timestamp);

    Notification::assertNothingSent();
});

it('requires a new verification when the email changes', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ada.king@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Profile updated. Check your inbox to confirm the new address.');

    $user->refresh();

    expect($user->email)->toBe('ada.king@example.com')
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('lets an unverified user fix a mistyped email', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['email' => 'ada.king@example.com']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('user.emailVerified', false));

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ada@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('ada@example.com');

    Notification::assertSentTo($user->fresh(), VerifyEmail::class);
});

it('rejects an email that already belongs to somebody else', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    User::factory()->create(['email' => 'grace@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'grace@example.com'])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('ada@example.com')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an invalid profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);

    expect($user->fresh()->name)->toBe($user->name);
});

it('never updates another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Mallory', 'email' => 'mallory@example.com'])
        ->assertSessionHasNoErrors();

    expect($other->fresh()->name)->toBe('Grace Hopper')
        ->and($other->fresh()->email)->toBe('grace@example.com');
});

it('updates the password and rotates the remember token', function () {
    $user = User::factory()->create();
    $token = $user->remember_token;

    $this->actingAs($user)
        ->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'a-brand-new-secret',
            'password_confirmation' => 'a-brand-new-secret',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.password'))
        ->assertSessionHas('success', 'Password updated.');

    $user->refresh();

    expect(Hash::check('a-brand-new-secret', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($token);
});

it('rejects a wrong current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.password.update'), [
            'current_password' => 'not-the-password',
            'password' => 'a-brand-new-secret',
            'password_confirmation' => 'a-brand-new-secret',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejects an unconfirmed password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'a-brand-new-secret',
            'password_confirmation' => 'a-different-secret',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('stores an uploaded avatar and serves it back', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('me.png', 64, 'image/png')])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Avatar updated.');

    $path = $user->fresh()->avatar_path;

    expect($path)->toStartWith("avatars/{$user->id}/")
        ->and($path)->toEndWith('.png');

    Storage::disk('local')->assertExists($path);

    $this->actingAs($user)
        ->get(route('profile.avatar'))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('replaces the avatar of a previous upload', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('me.png', 64, 'image/png')]);

    $previous = $user->fresh()->avatar_path;

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('me.jpg', 64, 'image/jpeg')]);

    $current = $user->fresh()->avatar_path;

    expect($current)->not->toBe($previous)
        ->and($current)->toEndWith('.jpg');

    Storage::disk('local')->assertMissing($previous);
    Storage::disk('local')->assertExists($current);
});

it('rejects an avatar that is not a supported image', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('notes.txt', 8, 'text/plain')])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar_path)->toBeNull();
});

it('rejects an avatar above the size limit', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('huge.png', 4096, 'image/png')])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar_path)->toBeNull();
});

it('removes the avatar', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('me.png', 64, 'image/png')]);

    $path = $user->fresh()->avatar_path;

    $this->actingAs($user)
        ->delete(route('profile.avatar.destroy'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Avatar removed.');

    expect($user->fresh()->avatar_path)->toBeNull();

    Storage::disk('local')->assertMissing($path);
});

it('has no avatar to serve before one is uploaded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.avatar'))
        ->assertNotFound();
});

it('shares the avatar url with the app shell', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('me.png', 64, 'image/png')]);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar', route('profile.avatar')));
});

it('shares no avatar url when none is uploaded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar', null));
});

it('requires authentication', function (string $method, string $uri) {
    $this->{$method}($uri)->assertRedirect(route('login'));
})->with([
    'profile page' => ['get', '/profile'],
    'password page' => ['get', '/profile/password'],
    'profile update' => ['patch', '/profile'],
    'password update' => ['put', '/profile/password'],
    'avatar upload' => ['post', '/profile/avatar'],
    'avatar removal' => ['delete', '/profile/avatar'],
    'avatar download' => ['get', '/profile/avatar'],
]);

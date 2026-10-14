<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns a successful response', function () {
    $this->get('/')->assertOk();
});

it('declares the TaskFlow site icons in the document head', function () {
    $this->get('/')
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('href="/favicon.ico"', false)
        ->assertSee('href="/apple-touch-icon.png"', false);
});

it('serves the site icon files', function () {
    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'] as $icon) {
        expect(public_path($icon))->toBeFile();
    }
});

it('renders the welcome page as an Inertia response', function () {
    $this->get('/')
        ->assertInertia(
            fn ($page) => $page
            ->component('Welcome')
            ->has('appName')
            ->has('version'),
        );
});

it('sends an authenticated visitor on to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

it('returns a failed sign-in attempt to the landing page', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->from('/')
        ->post(route('login'), [
            'email' => 'grace@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

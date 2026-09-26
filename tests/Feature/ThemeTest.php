<?php

declare(strict_types=1);

use App\Enums\Theme;
use App\Models\User;

it('defaults new users to the light theme', function () {
    $user = User::factory()->create();

    expect($user->fresh()->theme)->toBe(Theme::Light)
        ->and(User::factory()->create(['theme' => Theme::Dark])->theme)->toBe(Theme::Dark);
});

it('shares the stored theme with inertia', function () {
    $user = User::factory()->create(['theme' => Theme::Dark]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn ($page) => $page->component('Projects/Index')->where('theme', 'dark'));
});

it('shares the light theme with guests', function () {
    $this->get('/')
        ->assertInertia(fn ($page) => $page->component('Welcome')->where('theme', 'light'));
});

it('persists the selected theme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/theme', ['theme' => 'dark'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->fresh()->theme)->toBe(Theme::Dark);
});

it('rejects an unsupported theme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/theme', ['theme' => 'neon'])
        ->assertSessionHasErrors('theme');

    expect($user->fresh()->theme)->toBe(Theme::Light);
});

it('requires authentication to change the theme', function () {
    $this->patch('/theme', ['theme' => 'dark'])->assertRedirect(route('login'));

    expect(User::query()->where('theme', Theme::Dark->value)->exists())->toBeFalse();
});

it('only changes the theme of the acting user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->patch('/theme', ['theme' => 'dark'])->assertRedirect();

    expect($other->fresh()->theme)->toBe(Theme::Light);
});

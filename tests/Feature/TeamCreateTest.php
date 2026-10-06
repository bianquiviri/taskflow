<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

it('redirects a guest away from the team creation page', function () {
    $this->get(route('teams.create'))
        ->assertRedirect(route('login'));
});

it('redirects a guest away from creating a team', function () {
    $this->post(route('teams.store'), ['name' => 'Analytical Engines'])
        ->assertRedirect(route('login'));
});

it('renders the team creation page for a signed in user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('teams.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Teams/Create'));
});

it('creates a team owned by its creator', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Analytical Engines'])
        ->assertRedirect(route('teams.show', Team::sole()))
        ->assertSessionHas('success');

    $team = Team::sole();

    expect($team->name)->toBe('Analytical Engines')
        ->and($team->slug)->toBe('analytical-engines')
        ->and($team->owner->is($user))->toBeTrue()
        ->and($team->memberships()->count())->toBe(1)
        ->and($team->memberships()->first()->role)->toBe(TeamRole::Owner)
        ->and($team->memberships()->first()->user_id)->toBe($user->id);
});

it('keeps the slug unique when two teams share a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('teams.store'), ['name' => 'Acme'])->assertRedirect();
    $this->actingAs($user)->post(route('teams.store'), ['name' => 'Acme'])->assertRedirect();

    expect(Team::query()->pluck('slug')->all())->toBe(['acme', 'acme-2']);
});

it('rejects a team without a name', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('teams.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Team::count())->toBe(0);
});

it('rejects a team name longer than the column allows', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('teams.store'), ['name' => str_repeat('a', 256)])
        ->assertSessionHasErrors('name');

    expect(Team::count())->toBe(0);
});

it('lets every signed in user create a team', function () {
    $user = User::factory()->create();

    expect($user->can('teams.create'))->toBeTrue();
});

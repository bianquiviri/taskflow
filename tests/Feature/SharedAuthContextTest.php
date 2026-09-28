<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\AuthContext;
use Illuminate\Support\Facades\DB;

it('shares a lightweight authenticated user with every page', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(
            fn ($page) => $page
            ->component('Projects/Index')
            ->where('auth.user', [
                'id' => $user->id,
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ]),
        );
});

it('never leaks sensitive user fields to the frontend', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(function ($page): void {
            $shared = $page->toArray()['props']['auth']['user'];

            expect(array_keys($shared))->toBe(['id', 'name', 'email']);
        });
});

it('shares an empty auth context with guests', function () {
    $this->get('/')
        ->assertInertia(
            fn ($page) => $page
            ->component('Welcome')
            ->where('auth.user', null)
            ->where('auth.team', null)
            ->where('can', []),
        );
});

it('shares the current team with its role', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Analytical Engines', 'owner_id' => $user->id]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(
            fn ($page) => $page
            ->where('auth.team', [
                'id' => $team->id,
                'name' => 'Analytical Engines',
                'slug' => $team->slug,
                'role' => TeamRole::Owner->value,
            ]),
        );
});

it('takes the first team the user joined as the current team', function () {
    $user = User::factory()->create();
    $founder = User::factory()->create();

    Team::factory()->create(['owner_id' => $user->id]);
    $earlier = Team::factory()->create(['owner_id' => $founder->id]);

    TeamMember::factory()->create([
        'team_id' => $earlier->id,
        'user_id' => $user->id,
        'role' => TeamRole::Admin,
    ])->forceFill(['created_at' => now()->subWeek()])->save();

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(
            fn ($page) => $page
            ->where('auth.team.id', $earlier->id)
            ->where('auth.team.name', $earlier->name)
            ->where('auth.team.role', TeamRole::Admin->value),
        );
});

it('shares no team for users without a membership', function () {
    $user = User::factory()->create();
    Team::factory()->create(['owner_id' => User::factory()->create()->id]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(
            fn ($page) => $page
            ->where('auth.team', null)
            ->where('can', ['projects.viewAny', 'projects.create']),
        );
});

it('derives the permissions of a team manager from the policies', function () {
    $user = User::factory()->create();
    Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn ($page) => $page->where('can', [
            'projects.viewAny',
            'projects.create',
            'teams.view',
            'teams.manageMembers',
        ]));
});

it('derives the permissions of a plain team member from the policies', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    TeamMember::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => TeamRole::Member,
    ]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn ($page) => $page->where('can', [
            'projects.viewAny',
            'projects.create',
            'teams.view',
        ]));
});

it('shares the same auth context on every authenticated page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $expected = [
        'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug, 'role' => 'owner'],
        'can' => ['projects.viewAny', 'projects.create', 'teams.view', 'teams.manageMembers'],
    ];

    foreach (['/projects', route('projects.show', $project)] as $url) {
        $this->actingAs($user)
            ->get($url)
            ->assertInertia(
                fn ($page) => $page
                ->where('auth.user', $expected['user'])
                ->where('auth.team', $expected['team'])
                ->where('can', $expected['can']),
            );
    }
});

it('resolves the auth context only once per request', function () {
    $user = User::factory()->create();
    Team::factory()->create(['owner_id' => $user->id]);

    $context = app(AuthContext::class);

    DB::enableQueryLog();

    $first = $context->resolve($user);
    $afterFirst = count(DB::getQueryLog());

    $second = $context->resolve($user);
    $afterSecond = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($afterFirst)->toBeGreaterThan(0)
        ->and($afterSecond)->toBe($afterFirst)
        ->and($second)->toBe($first);
});

it('does not query the database for a guest context', function () {
    DB::enableQueryLog();

    $context = app(AuthContext::class);
    $first = $context->resolve(null);
    $queries = count(DB::getQueryLog());

    $second = $context->resolve(null);

    DB::disableQueryLog();

    expect($first)->toBe(['user' => null, 'team' => null, 'can' => []])
        ->and($second)->toBe($first)
        ->and($queries)->toBe(0);
});

it('never serves the cached context of another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $context = app(AuthContext::class);

    $context->resolve($user);

    expect($context->resolve($other)['user']['id'])->toBe($other->id)
        ->and($context->resolve($user)['user']['id'])->toBe($user->id);
});

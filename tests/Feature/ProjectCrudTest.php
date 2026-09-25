<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

it('lets an authenticated user create a project with an owner membership', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'Website Redesign',
            'description' => 'Revamp the marketing site.',
        ])
        ->assertRedirect(route('projects.show', Project::first()));

    $this->assertDatabaseHas('projects', [
        'name' => 'Website Redesign',
        'owner_id' => $user->id,
    ]);
    $this->assertDatabaseHas('project_members', [
        'project_id' => Project::first()->id,
        'user_id' => $user->id,
        'role' => ProjectRole::Owner->value,
    ]);
});

it('validates the project name on creation', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('lists the active projects the user owns or belongs to', function () {
    $user = User::factory()->create();
    $owned = Project::factory()->create([
        'owner_id' => $user->id,
        'created_at' => now()->subHours(2),
    ]);

    $otherUser = User::factory()->create();
    $other = Project::factory()->create([
        'owner_id' => $otherUser->id,
        'created_at' => now()->subHour(),
    ]);
    $other->members()->create(['user_id' => $user->id, 'role' => ProjectRole::Member]);

    Project::factory()->create(['owner_id' => User::factory()->create()->id]);

    Project::factory()->archived()->create([
        'owner_id' => $user->id,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Projects/Index')
            ->has('projects', 2)
            ->where('projects.0.id', $other->id)
            ->where('projects.1.id', $owned->id),
        );
});

it('shows a project to the owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Projects/Show')
            ->where('project.id', $project->id),
        );
});

it('lets the owner update a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['name' => 'Renamed Project'])
        ->assertRedirect(route('projects.show', $project));

    expect($project->refresh()->name)->toBe('Renamed Project');
});

it('archives a project and hides it from the active index', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->patch(route('projects.archive', $project))
        ->assertRedirect(route('projects.index'));

    expect($project->refresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

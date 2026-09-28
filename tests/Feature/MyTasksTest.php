<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('lists only the tasks assigned to the signed in user', function () {
    $user = User::factory()->create();
    $teammate = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $project->members()->create(['user_id' => $teammate->id, 'role' => 'member']);

    $mine = Task::factory()->for($project, 'project')->for($user, 'assignee')->create(['position' => 1]);
    Task::factory()->for($project, 'project')->for($teammate, 'assignee')->create(['position' => 2]);
    Task::factory()->for($project, 'project')->create(['position' => 3]);

    $this->actingAs($user)
        ->get(route('tasks.mine.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Mine')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $mine->id)
            ->where('tasks.data.0.assignee.id', $user->id)
            ->where('tasks.data.0.project.id', $project->id)
            ->where('tasks.total', 1),
        );
});

it('gathers my tasks from every project I belong to', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $owned = Project::factory()->for($user, 'owner')->create(['name' => 'Website']);
    $joined = Project::factory()->for($other, 'owner')->create(['name' => 'Platform']);
    $joined->members()->create(['user_id' => $user->id, 'role' => 'member']);

    $inOwned = Task::factory()->for($owned, 'project')->for($user, 'assignee')->create(['due_date' => '2026-10-05']);
    $inJoined = Task::factory()->for($joined, 'project')->for($user, 'assignee')->create(['due_date' => '2026-11-20']);

    $this->actingAs($user)
        ->get(route('tasks.mine.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 2)
            ->where('tasks.data.0.id', $inOwned->id)
            ->where('tasks.data.1.id', $inJoined->id),
        );
});

it('hides my tasks from projects I no longer belong to', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($other, 'owner')->create();
    $membership = $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
    Task::factory()->for($project, 'project')->for($user, 'assignee')->create();

    $membership->delete();

    $this->actingAs($user)
        ->get(route('tasks.mine.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 0)
            ->where('tasks.total', 0),
        );
});

it('filters my tasks with the shared filters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $matching = Task::factory()->for($project, 'project')->for($user, 'assignee')->create([
        'title' => 'Refresh the changelog',
        'description' => 'Summarise the merged pull requests',
        'status' => TaskStatus::InReview,
        'priority' => 'urgent',
        'due_date' => '2026-10-12',
        'position' => 1,
    ]);
    Task::factory()->for($project, 'project')->for($user, 'assignee')->create([
        'title' => 'Refresh the changelog',
        'status' => TaskStatus::Todo,
        'priority' => 'urgent',
        'due_date' => '2026-10-12',
        'position' => 2,
    ]);
    Task::factory()->for($project, 'project')->for($user, 'assignee')->create([
        'title' => 'Book the venue',
        'status' => TaskStatus::InReview,
        'priority' => 'urgent',
        'due_date' => '2026-10-12',
        'position' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('tasks.mine.index', [
            'search' => 'pull requests',
            'status' => 'in_review',
            'priority' => 'urgent',
            'due_from' => '2026-10-01',
            'due_to' => '2026-10-31',
        ]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $matching->id)
            ->where('filters.search', 'pull requests')
            ->where('filters.status', 'in_review'),
        );
});

it('ignores an assignee filter on my tasks', function () {
    $user = User::factory()->create();
    $teammate = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $project->members()->create(['user_id' => $teammate->id, 'role' => 'member']);

    $mine = Task::factory()->for($project, 'project')->for($user, 'assignee')->create();
    Task::factory()->for($project, 'project')->for($teammate, 'assignee')->create();

    $this->actingAs($user)
        ->get(route('tasks.mine.index', ['assignee_id' => $teammate->id]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $mine->id),
        );
});

it('paginates my tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $tasks = Task::factory()->count(16)->for($project, 'project')->for($user, 'assignee')->create();

    $this->actingAs($user)
        ->get(route('tasks.mine.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 15)
            ->where('tasks.data.0.id', $tasks->first()->id)
            ->where('tasks.total', 16)
            ->where('tasks.last_page', 2)
            ->has('tasks.links', 4),
        );

    $this->actingAs($user)
        ->get(route('tasks.mine.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $tasks->last()->id),
        );
});

it('offers only the shared filter options on my tasks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.mine.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('filterOptions.statuses.1.value', 'in_progress')
            ->where('filterOptions.priorities.0.value', 'low')
            ->has('filterOptions.assignees', 0),
        );
});

it('rejects invalid filters on my tasks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.mine.index', ['status' => 'nope']))
        ->assertSessionHasErrors('status');
});

it('keeps my tasks behind authentication', function () {
    $this->get(route('tasks.mine.index'))
        ->assertRedirect(route('login'));
});

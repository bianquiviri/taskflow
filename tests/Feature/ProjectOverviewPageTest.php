<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

it('gives the project page the people working on it, the owner first', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $admin = User::factory()->create(['name' => 'Grace Hopper']);
    $member = User::factory()->create(['name' => 'Alan Turing']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $owner->id, 'role' => ProjectRole::Owner]);
    $project->members()->create(['user_id' => $admin->id, 'role' => ProjectRole::Admin]);
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('members', [
                ['id' => $owner->id, 'name' => 'Ada Lovelace', 'role' => ProjectRole::Owner->value],
                ['id' => $member->id, 'name' => 'Alan Turing', 'role' => ProjectRole::Member->value],
                ['id' => $admin->id, 'name' => 'Grace Hopper', 'role' => ProjectRole::Admin->value],
            ]),
        );
});

it('lists the project owner even without a membership row', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertInertia(
            fn ($page) => $page
            ->has('members', 1)
            ->where('members.0.id', $owner->id)
            ->where('members.0.role', ProjectRole::Owner->value),
        );
});

it('derives the project progress from the status of its tasks', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->count(2)->for($project, 'project')->create(['status' => TaskStatus::Done]);
    Task::factory()->count(5)->for($project, 'project')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('progress', [
            'total' => 7,
            'done' => 2,
            'percent' => 29,
        ]));
});

it('reports no progress for a project without tasks', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('progress', [
            'total' => 0,
            'done' => 0,
            'percent' => 0,
        ]));
});

it('sends the board the filtered tasks, the filters and their options', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => ProjectRole::Member]);

    $done = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Ship the release',
        'status' => TaskStatus::Done,
        'priority' => TaskPriority::High,
    ]);
    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Write the notes',
        'status' => TaskStatus::Todo,
    ]);
    Task::factory()->for($project, 'project')->create([
        'title' => 'Archive the sprint',
        'status' => TaskStatus::Done,
    ]);

    $this->actingAs($owner)
        ->get(route('projects.show', ['project' => $project, 'status' => 'done', 'search' => 'ship']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('filters.status', 'done')
            ->where('filters.search', 'ship')
            ->where('filters.assignee_id', null)
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $done->id)
            ->where('tasks.total', 1)
            ->has('filterOptions.statuses', 5)
            ->has('filterOptions.priorities', 4)
            ->has('filterOptions.assignees', 2),
        );
});

it('defaults the project page to the board and honours the list view', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('view', 'board'));

    $this->actingAs($owner)
        ->get(route('projects.show', ['project' => $project, 'view' => 'list']))
        ->assertInertia(fn ($page) => $page->where('view', 'list'));

    $this->actingAs($owner)
        ->get(route('projects.show', ['project' => $project, 'view' => 'chart']))
        ->assertInertia(fn ($page) => $page->where('view', 'board'));
});

it('grants a project manager every task on the page', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('permissions', [
            'tasks.create',
            "tasks.{$task->id}.changeStatus",
            "tasks.{$task->id}.update",
        ]));
});

it('grants a plain member only the tasks assigned to them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $assigned = Task::factory()->for($project, 'project')->for($member, 'assignee')->create();
    $unassigned = Task::factory()->for($project, 'project')->create();

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('permissions', [
            "tasks.{$assigned->id}.changeStatus",
            "tasks.{$assigned->id}.update",
        ]));
});

it('resolves the permissions of the tasks on screen only', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $onScreen = Task::factory()->for($project, 'project')->create();
    $nextPage = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', ['project' => $project, 'search' => $onScreen->title]))
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('permissions', fn (Collection $permissions): bool => $permissions->contains("tasks.{$onScreen->id}.changeStatus")
                && ! $permissions->contains("tasks.{$nextPage->id}.changeStatus")),
        );
});

it('still shares the global auth context next to the page permissions', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertInertia(
            fn ($page) => $page
            ->where('can', ['projects.viewAny', 'projects.create'])
            ->where('auth.user.id', $owner->id),
        );
});

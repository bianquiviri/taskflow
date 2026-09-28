<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

function boardQuery(Project $project, array $query = []): array
{
    return ['project' => $project, ...$query];
}

it('filters the project board by status', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $inProgress = Task::factory()->for($project, 'project')->create(['status' => TaskStatus::InProgress]);
    Task::factory()->for($project, 'project')->create(['status' => TaskStatus::Done]);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['status' => 'in_progress'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Index')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $inProgress->id)
            ->where('tasks.total', 1),
        );
});

it('filters the project board by priority', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $urgent = Task::factory()->for($project, 'project')->create(['priority' => 'urgent']);
    Task::factory()->for($project, 'project')->create(['priority' => 'low']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['priority' => 'urgent'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $urgent->id),
        );
});

it('filters the project board by assignee', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => 'member']);
    $assigned = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create();
    Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['assignee_id' => $assignee->id])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $assigned->id),
        );
});

it('filters the project board by a due date range', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $inRange = Task::factory()->for($project, 'project')->create(['due_date' => '2026-10-12']);
    Task::factory()->for($project, 'project')->create(['due_date' => '2026-09-28']);
    Task::factory()->for($project, 'project')->create(['due_date' => '2026-11-30']);
    Task::factory()->for($project, 'project')->create(['due_date' => null]);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, [
            'due_from' => '2026-10-01',
            'due_to' => '2026-10-31',
        ])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $inRange->id),
        );
});

it('filters the project board by an open due date range', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $overdue = Task::factory()->for($project, 'project')->create(['due_date' => '2026-09-01']);
    $upcoming = Task::factory()->for($project, 'project')->create(['due_date' => '2026-12-01']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['due_to' => '2026-09-30'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $overdue->id),
        );

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['due_from' => '2026-11-01'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $upcoming->id),
        );
});

it('searches tasks by title', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $match = Task::factory()->for($project, 'project')->create(['title' => 'Fix the login redirect']);
    Task::factory()->for($project, 'project')->create(['title' => 'Write the release notes']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => 'login redirect'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $match->id),
        );
});

it('searches tasks by description', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $match = Task::factory()->for($project, 'project')->create([
        'title' => 'Audit the queue',
        'description' => 'Redis keeps the notifications backlog',
    ]);
    Task::factory()->for($project, 'project')->create([
        'title' => 'Audit the cache',
        'description' => 'Clear the stale keys',
    ]);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => 'notifications'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $match->id),
        );
});

it('searches case insensitively on partial terms', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $match = Task::factory()->for($project, 'project')->create(['title' => 'Refactor the Onboarding Flow']);
    Task::factory()->for($project, 'project')->create(['title' => 'Bump dependencies']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => 'ONBOARD'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $match->id),
        );
});

it('ignores a blank search term', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->count(2)->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => '   '])))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks.data', 2));
});

it('never searches outside the requested project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $otherProject = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->for($otherProject, 'project')->create(['title' => 'Login page copy']);
    Task::factory()->for($project, 'project')->create(['title' => 'Unrelated work']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => 'login'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 0)
            ->where('tasks.total', 0),
        );
});

it('combines every filter', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => 'member']);

    $matching = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Harden the auth flow',
        'description' => 'Rate limit the login endpoint',
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-10-12',
        'position' => 1,
    ]);

    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Harden the auth flow',
        'status' => TaskStatus::Todo,
        'priority' => 'high',
        'due_date' => '2026-10-12',
        'position' => 2,
    ]);
    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Harden the auth flow',
        'status' => TaskStatus::InProgress,
        'priority' => 'low',
        'due_date' => '2026-10-12',
        'position' => 3,
    ]);
    Task::factory()->for($project, 'project')->create([
        'title' => 'Harden the auth flow',
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-10-12',
        'position' => 4,
    ]);
    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Harden the auth flow',
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-12-01',
        'position' => 5,
    ]);
    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Ship the auth hardening',
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-10-12',
        'position' => 6,
    ]);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, [
            'search' => 'rate limit',
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee_id' => $assignee->id,
            'due_from' => '2026-10-01',
            'due_to' => '2026-10-31',
        ])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $matching->id),
        );
});

it('splits a crowded project board into ordered pages', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $tasks = Task::factory()->count(16)->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project)))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 15)
            ->where('tasks.data.0.id', $tasks->first()->id)
            ->where('tasks.data.14.id', $tasks[14]->id)
            ->where('tasks.total', 16)
            ->where('tasks.per_page', 15)
            ->where('tasks.last_page', 2)
            ->has('tasks.links', 4),
        );

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['page' => 2])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $tasks->last()->id)
            ->where('tasks.current_page', 2),
        );
});

it('keeps the filters and the page in the paginator links', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->count(16)->for($project, 'project')->create(['status' => TaskStatus::InProgress]);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['status' => 'in_progress'])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('tasks.links.2.url', fn (string $url): bool => str_contains($url, 'status=in_progress')
                && str_contains($url, 'page=2')),
        );
});

it('rejects invalid filter values', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['status' => 'nope'])))
        ->assertSessionHasErrors('status');

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['priority' => 'nope'])))
        ->assertSessionHasErrors('priority');

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['due_from' => 'not-a-date'])))
        ->assertSessionHasErrors('due_from');

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, [
            'due_from' => '2026-10-31',
            'due_to' => '2026-10-01',
        ])))
        ->assertSessionHasErrors('due_to');

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => ['array']])))
        ->assertSessionHasErrors('search');
});

it('rejects a page number below one', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, ['page' => 0])))
        ->assertSessionHasErrors('page');
});

it('echoes the applied filters back for shareable urls', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => 'member']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project, [
            'search' => '  auth  ',
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee_id' => $assignee->id,
            'due_from' => '2026-10-01',
            'due_to' => '2026-10-31',
        ])))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('filters.search', 'auth')
            ->where('filters.status', 'in_progress')
            ->where('filters.priority', 'high')
            ->where('filters.assignee_id', $assignee->id)
            ->where('filters.due_from', '2026-10-01')
            ->where('filters.due_to', '2026-10-31'),
        );
});

it('exposes the filter options of the project board', function () {
    $owner = User::factory()->create(['name' => 'Zoe Owner']);
    $member = User::factory()->create(['name' => 'Alan Turing']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', boardQuery($project)))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('filterOptions.statuses.0.value', 'todo')
            ->where('filterOptions.statuses.0.label', 'To Do')
            ->where('filterOptions.priorities.3.value', 'urgent')
            ->where('filterOptions.priorities.3.label', 'Urgent')
            ->has('filterOptions.assignees', 2)
            ->where('filterOptions.assignees.0.id', $member->id)
            ->where('filterOptions.assignees.0.name', 'Alan Turing')
            ->where('filterOptions.assignees.1.id', $owner->id),
        );
});

it('keeps the project board closed to outsiders', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($outsider)
        ->get(route('projects.tasks.index', boardQuery($project, ['search' => 'anything'])))
        ->assertForbidden();
});

it('keeps the project board behind authentication', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.tasks.index', $project))
        ->assertRedirect(route('login'));
});

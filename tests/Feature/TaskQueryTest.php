<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskQuery;

it('eager loads the relations a listing renders', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project, 'project')->create();

    $listed = TaskQuery::make()->get();

    expect($listed)->toHaveCount(1)
        ->and($listed->first()->relationLoaded('assignee'))->toBeTrue()
        ->and($listed->first()->relationLoaded('project'))->toBeTrue()
        ->and($listed->first()->is($task))->toBeTrue();
});

it('restricts a query to a project', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $task = Task::factory()->for($project, 'project')->create();
    Task::factory()->for($other, 'project')->create();

    expect(TaskQuery::make()->forProject($project)->get()->pluck('id')->all())->toBe([$task->id]);
});

it('restricts a query to the projects of a user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $owned = Project::factory()->for($user, 'owner')->create();
    $joined = Project::factory()->for($other, 'owner')->create();
    $joined->members()->create(['user_id' => $user->id, 'role' => 'member']);
    $forbidden = Project::factory()->for($other, 'owner')->create();

    $visible = Task::factory()->for($owned, 'project')->create();
    $alsoVisible = Task::factory()->for($joined, 'project')->create();
    $hidden = Task::factory()->for($forbidden, 'project')->create();

    $ids = TaskQuery::make()->forProjectsOf($user)->get()->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$visible->id, $alsoVisible->id])
        ->and($ids)->not->toContain($hidden->id);
});

it('chains the status, priority, assignee, due and search filters', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => 'member']);

    $matching = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'title' => 'Ship the release',
        'description' => 'Tag and publish the images',
        'status' => TaskStatus::InProgress,
        'priority' => 'urgent',
        'due_date' => '2026-10-12',
    ]);
    Task::factory()->for($project, 'project')->create([
        'title' => 'Ship the release',
        'status' => TaskStatus::InProgress,
        'priority' => 'urgent',
        'due_date' => '2026-10-12',
    ]);

    $ids = TaskQuery::make()
        ->status('in_progress')
        ->priority('urgent')
        ->assignedTo($assignee->id)
        ->due('2026-10-01', '2026-10-31')
        ->search('publish the images')
        ->get()
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$matching->id]);
});

it('skips empty filter values', function () {
    $project = Project::factory()->create();
    Task::factory()->count(2)->for($project, 'project')->create();

    $ids = TaskQuery::make()
        ->status(null)
        ->priority('')
        ->assignedTo(null)
        ->due(null, null)
        ->search('   ')
        ->get()
        ->pluck('id')
        ->all();

    expect($ids)->toHaveCount(2);
});

it('accepts a user as the assignee filter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $mine = Task::factory()->for($project, 'project')->for($user, 'assignee')->create();
    Task::factory()->for($project, 'project')->create();

    expect(TaskQuery::make()->assignedTo($user)->get()->pluck('id')->all())->toBe([$mine->id]);
});

it('applies a normalised filter array at once', function () {
    $project = Project::factory()->create();
    $matching = Task::factory()->for($project, 'project')->create([
        'title' => 'Deploy the API',
        'status' => TaskStatus::Done,
    ]);
    Task::factory()->for($project, 'project')->create(['title' => 'Deploy the API', 'status' => TaskStatus::Todo]);

    $ids = TaskQuery::make()
        ->apply(['search' => ' api ', 'status' => 'done', 'priority' => null, 'assignee_id' => null, 'due_from' => null, 'due_to' => null])
        ->get()
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$matching->id]);
});

it('orders a board by position and my tasks by due date', function () {
    $project = Project::factory()->create();
    $late = Task::factory()->for($project, 'project')->create(['due_date' => '2026-12-01', 'position' => 1]);
    $early = Task::factory()->for($project, 'project')->create(['due_date' => '2026-10-01', 'position' => 2]);
    $undated = Task::factory()->for($project, 'project')->create(['due_date' => null, 'position' => 3]);

    expect(TaskQuery::make()->orderByPosition()->get()->pluck('id')->all())
        ->toBe([$late->id, $early->id, $undated->id])
        ->and(TaskQuery::make()->orderByDueDate()->get()->pluck('id')->all())
        ->toBe([$early->id, $late->id, $undated->id]);
});

it('paginates with the requested page size', function () {
    $project = Project::factory()->create();
    $tasks = Task::factory()->count(4)->for($project, 'project')->create();

    $page = TaskQuery::make()->forProject($project)->orderByPosition()->paginate(2);

    expect($page->total())->toBe(4)
        ->and($page->perPage())->toBe(2)
        ->and($page->lastPage())->toBe(2)
        ->and($page->pluck('id')->all())->toBe([$tasks->first()->id, $tasks[1]->id]);
});

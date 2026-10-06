<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskQueryService;

it('paginates a project board in position order', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $tasks = Task::factory()->count(16)->for($project, 'project')->create();

    $page = (new TaskQueryService())->forProject($project, []);

    expect($page->total())->toBe(16)
        ->and($page->perPage())->toBe(TaskQueryService::PER_PAGE)
        ->and($page->lastPage())->toBe(2)
        ->and($page->getCollection()->pluck('id')->all())->toBe($tasks->take(15)->pluck('id')->all());
});

it('applies every filter of a project board', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => 'member']);

    $matching = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'description' => 'Rate limit the endpoint',
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-10-12',
    ]);
    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'status' => TaskStatus::Todo,
        'priority' => 'high',
        'due_date' => '2026-10-12',
    ]);

    $page = (new TaskQueryService())->forProject($project, [
        'search' => 'rate limit',
        'status' => 'in_progress',
        'priority' => 'high',
        'assignee_id' => $assignee->id,
        'due_from' => '2026-10-01',
        'due_to' => '2026-10-31',
    ]);

    expect($page->pluck('id')->all())->toBe([$matching->id]);
});

it('returns the tasks of a user across their projects by due date', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $owned = Project::factory()->for($user, 'owner')->create();
    $joined = Project::factory()->for($other, 'owner')->create();
    $joined->members()->create(['user_id' => $user->id, 'role' => 'member']);

    $later = Task::factory()->for($owned, 'project')->for($user, 'assignee')->create(['due_date' => '2026-12-01']);
    $earlier = Task::factory()->for($joined, 'project')->for($user, 'assignee')->create(['due_date' => '2026-10-01']);
    Task::factory()->for($owned, 'project')->for($other, 'assignee')->create();

    $page = (new TaskQueryService())->forAssignee($user, []);

    expect($page->pluck('id')->all())->toBe([$earlier->id, $later->id])
        ->and($page->perPage())->toBe(TaskQueryService::PER_PAGE);
});

it('applies the filters of a my tasks listing', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $matching = Task::factory()->for($project, 'project')->for($user, 'assignee')->create([
        'title' => 'Book the venue',
        'status' => TaskStatus::Done,
    ]);
    Task::factory()->for($project, 'project')->for($user, 'assignee')->create([
        'title' => 'Book the venue',
        'status' => TaskStatus::Todo,
    ]);

    $page = (new TaskQueryService())->forAssignee($user, [
        'search' => 'venue',
        'status' => 'done',
        'priority' => null,
        'assignee_id' => null,
        'due_from' => null,
        'due_to' => null,
    ]);

    expect($page->pluck('id')->all())->toBe([$matching->id]);
});

it('lists the statuses and priorities as filter options', function () {
    $options = (new TaskQueryService())->filterOptions();

    expect($options['statuses'])->toHaveCount(count(TaskStatus::cases()))
        ->and($options['statuses'][0])->toBe(['value' => 'todo', 'label' => 'To Do'])
        ->and($options['priorities'])->toHaveCount(4)
        ->and($options['priorities'][0])->toBe(['value' => 'low', 'label' => 'Low'])
        ->and($options['assignees'])->toBe([]);
});

it('lists the owner and the members of a project as assignee options', function () {
    $owner = User::factory()->create(['name' => 'Zoe Owner']);
    $member = User::factory()->create(['name' => 'Alan Turing']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);

    $assignees = (new TaskQueryService())->filterOptions($project)['assignees'];

    expect($assignees)->toHaveCount(2)
        ->and($assignees[0])->toBe(['id' => $member->id, 'name' => 'Alan Turing'])
        ->and($assignees[1]['name'])->toBe('Zoe Owner');
});

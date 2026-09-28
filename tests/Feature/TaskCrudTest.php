<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskOrderService;
use Illuminate\Validation\ValidationException;

it('creates a task at the end of its project order', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->for($project, 'project')->create(['position' => 7]);

    $this->actingAs($owner)
        ->post(route('projects.tasks.store', $project), ['title' => 'Write release notes'])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'Write release notes',
        'status' => TaskStatus::Todo->value,
        'position' => 8,
    ]);
});

it('lists only tasks matching all filters in position order', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $assignee->id, 'role' => 'member']);

    $matching = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-10-12',
        'position' => 1,
    ]);
    Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'status' => TaskStatus::Todo,
        'priority' => 'high',
        'due_date' => '2026-10-12',
        'position' => 2,
    ]);
    Task::factory()->for($project, 'project')->create([
        'status' => TaskStatus::InProgress,
        'priority' => 'high',
        'due_date' => '2026-10-20',
        'position' => 3,
    ]);

    $this->actingAs($owner)
        ->get(route('projects.tasks.index', [
            'project' => $project,
            'assignee_id' => $assignee->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'due_from' => '2026-10-01',
            'due_to' => '2026-10-15',
        ]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Index')
            ->where('project.id', $project->id)
            ->where('tasks.data.0.id', $matching->id)
            ->has('tasks.data', 1),
        );
});

it('lets an assigned member update and change status', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
    $task = Task::factory()->for($project, 'project')->for($member, 'assignee')->create([
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($member)
        ->patch(route('tasks.update', $task), [
            'title' => 'Updated title',
            'priority' => 'urgent',
        ])
        ->assertRedirect(route('tasks.show', $task));

    expect($task->refresh()->title)->toBe('Updated title')
        ->and($task->priority->value)->toBe('urgent')
        ->and($task->status)->toBe(TaskStatus::Todo);

    $this->actingAs($member)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect(route('tasks.show', $task));

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);
});

it('rejects invalid status transitions', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create([
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($owner)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertSessionHasErrors('status');

    expect($task->refresh()->status)->toBe(TaskStatus::Todo);
});

it('lets a project manager assign and unassign a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->patch(route('tasks.assign', $task), ['assignee_id' => $member->id])
        ->assertRedirect(route('tasks.show', $task));

    expect($task->refresh()->assignee->is($member))->toBeTrue();

    $this->actingAs($owner)
        ->patch(route('tasks.assign', $task), ['assignee_id' => null])
        ->assertRedirect(route('tasks.show', $task));

    expect($task->refresh()->assignee_id)->toBeNull();
});

it('rejects assigning a user outside the project', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->patch(route('tasks.assign', $task), ['assignee_id' => $outsider->id])
        ->assertSessionHasErrors('assignee_id');

    expect($task->refresh()->assignee_id)->toBeNull();
});

it('reorders every task in a project from one to its final position', function () {
    $project = Project::factory()->create();
    $first = Task::factory()->for($project, 'project')->create(['position' => 1]);
    $second = Task::factory()->for($project, 'project')->create(['position' => 2]);
    $third = Task::factory()->for($project, 'project')->create(['position' => 3]);

    (new TaskOrderService())->reorder($project, [$third->id, $first->id, $second->id]);

    expect(Task::forProject($project)->orderBy('position')->pluck('id')->all())
        ->toBe([$third->id, $first->id, $second->id])
        ->and($third->refresh()->position)->toBe(1)
        ->and($first->refresh()->position)->toBe(2)
        ->and($second->refresh()->position)->toBe(3);
});

it('rejects incomplete duplicated or cross-project task orders', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $task = Task::factory()->for($project, 'project')->create();
    Task::factory()->for($project, 'project')->create();
    $otherTask = Task::factory()->for($otherProject, 'project')->create();
    $service = new TaskOrderService();

    expect(fn () => $service->reorder($project, [$task->id]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->reorder($project, [$task->id, $task->id]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->reorder($project, [$task->id, $otherTask->id]))
        ->toThrow(ValidationException::class)
        ->and($task->refresh()->position)->toBe(0);
});

it('sends the priorities and the abilities of a task to its page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
    $assigned = Task::factory()->for($project, 'project')->for($member, 'assignee')->create();
    $other = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('tasks.show', $assigned))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Show')
            ->where('priorities', [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High'],
                ['value' => 'urgent', 'label' => 'Urgent'],
            ])
            ->where('permissions', ['tasks.update', 'tasks.changeStatus']),
        );

    $this->actingAs($member)
        ->get(route('tasks.show', $assigned))
        ->assertInertia(fn ($page) => $page->where('permissions', ['tasks.update', 'tasks.changeStatus']));

    $this->actingAs($member)
        ->get(route('tasks.show', $other))
        ->assertInertia(fn ($page) => $page->where('permissions', []));
});

it('keeps a task mutation started from Inertia on the page it came from', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
    $task = Task::factory()->for($project, 'project')->create();
    $board = route('projects.show', $project);

    $this->actingAs($owner);

    $this->withHeader('X-Inertia', 'true')
        ->from($board)
        ->post(route('projects.tasks.store', $project), ['title' => 'Write the notes'])
        ->assertRedirect($board);

    $this->withHeader('X-Inertia', 'true')
        ->from($board)
        ->patch(route('tasks.update', $task), ['title' => 'Renamed task'])
        ->assertRedirect($board);

    $this->withHeader('X-Inertia', 'true')
        ->from($board)
        ->patch(route('tasks.status', $task), ['status' => TaskStatus::InProgress->value])
        ->assertRedirect($board);

    $this->withHeader('X-Inertia', 'true')
        ->from($board)
        ->patch(route('tasks.assign', $task), ['assignee_id' => $member->id])
        ->assertRedirect($board);

    expect($task->refresh()->title)->toBe('Renamed task')
        ->and($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->assignee_id)->toBe($member->id);
});

it('still reports a rejected status change sent from Inertia', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('projects.show', $project))
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHasErrors('status');

    expect($task->refresh()->status)->toBe(TaskStatus::Todo);
});

<?php

declare(strict_types=1);

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('casts task state fields and exposes its relationships', function () {
    $project = Project::factory()->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'status' => TaskStatus::InProgress,
        'priority' => TaskPriority::Urgent,
        'due_date' => '2026-10-15',
        'position' => 4,
    ]);

    expect($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->priority)->toBe(TaskPriority::Urgent)
        ->and($task->due_date)->toBeInstanceOf(DateTimeInterface::class)
        ->and($task->position)->toBe(4)
        ->and($task->project->is($project))->toBeTrue()
        ->and($task->assignee->is($assignee))->toBeTrue();
});

it('filters tasks by project assignee status priority and due date', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $assignee = User::factory()->create();
    $otherAssignee = User::factory()->create();

    $matching = Task::factory()->for($project, 'project')->for($assignee, 'assignee')->create([
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'due_date' => '2026-10-10',
    ]);
    $otherInProject = Task::factory()->for($project, 'project')->for($otherAssignee, 'assignee')->create([
        'status' => TaskStatus::InProgress,
        'priority' => TaskPriority::Urgent,
        'due_date' => '2026-10-20',
    ]);
    Task::factory()->for($otherProject, 'project')->for($otherAssignee, 'assignee')->create([
        'status' => TaskStatus::InProgress,
        'priority' => TaskPriority::Urgent,
        'due_date' => '2026-10-30',
    ]);

    expect(Task::forProject($project)->pluck('id')->all())
        ->toEqualCanonicalizing([$matching->id, $otherInProject->id])
        ->and(Task::assignedTo($assignee)->pluck('id')->all())->toEqualCanonicalizing([$matching->id])
        ->and(Task::withStatus(TaskStatus::Todo)->pluck('id')->all())->toEqualCanonicalizing([$matching->id])
        ->and(Task::withPriority(TaskPriority::Low)->pluck('id')->all())->toEqualCanonicalizing([$matching->id])
        ->and(Task::dueBetween('2026-10-10', '2026-10-20')->pluck('id')->all())
        ->toEqualCanonicalizing([$matching->id, $otherInProject->id]);
});

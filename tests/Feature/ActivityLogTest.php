<?php

declare(strict_types=1);

use App\Actions\ArchiveProjectAction;
use App\Actions\ChangeTaskStatusAction;
use App\Actions\CreateTaskAction;
use App\Actions\UpdateProjectAction;
use App\Actions\UpdateTaskAction;
use App\Enums\ActivityEvent;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('records a task creation attributed to the acting user', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner);

    $task = (new CreateTaskAction())($project, ['title' => 'Write release notes']);

    $log = ActivityLog::forSubject($task)->sole();

    expect($log->event)->toBe(ActivityEvent::TaskCreated)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->subject_type)->toBe('task')
        ->and($log->subject_id)->toBe($task->id)
        ->and($log->meta)->toBe(['title' => 'Write release notes', 'status' => 'todo']);
});

it('records a task update with the changed attributes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner);

    (new UpdateTaskAction())($task, ['title' => 'Renamed task']);

    $log = ActivityLog::forSubject($task)->latest('id')->first();

    expect($log->event)->toBe(ActivityEvent::TaskUpdated)
        ->and($log->meta)->toBe(['changes' => ['title' => 'Renamed task']]);
});

it('records a status change instead of a plain task update', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner);

    (new ChangeTaskStatusAction())($task, TaskStatus::InProgress);

    $logs = ActivityLog::forSubject($task)->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->last()->event)->toBe(ActivityEvent::TaskStatusChanged)
        ->and($logs->last()->meta)->toBe(['from' => 'todo', 'to' => 'in_progress']);
});

it('records project creation and update entries', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create(['name' => 'Website Redesign']);

    $this->actingAs($owner);

    (new UpdateProjectAction())($project, ['name' => 'Website Relaunch']);

    $logs = ActivityLog::forSubject($project)->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->first()->event)->toBe(ActivityEvent::ProjectCreated)
        ->and($logs->first()->meta)->toBe(['name' => 'Website Redesign'])
        ->and($logs->last()->event)->toBe(ActivityEvent::ProjectUpdated)
        ->and($logs->last()->meta)->toBe(['changes' => ['name' => 'Website Relaunch']]);
});

it('records an archive entry with an empty meta payload', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner);

    (new ArchiveProjectAction())($project);

    $log = ActivityLog::forSubject($project)->latest('id')->first();

    expect($log->event)->toBe(ActivityEvent::ProjectArchived)
        ->and($log->meta)->toBeNull();
});

it('leaves the actor empty when nobody is authenticated', function () {
    $project = Project::factory()->create();

    (new ArchiveProjectAction())($project);

    expect(ActivityLog::forSubject($project)->latest('id')->first()->actor_id)->toBeNull();
});

it('resolves the actor and the subject of an entry', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner);

    (new ArchiveProjectAction())($project);

    $log = ActivityLog::forSubject($project)
        ->latest('id')
        ->with(['actor', 'subject'])
        ->first();

    expect($log->actor->is($owner))->toBeTrue()
        ->and($log->subject->is($project))->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Mcp\Tools\Tasks\UpdateTaskTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdatedNotification;
use App\Support\IdCipher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('updates a task addressed by its encrypted key', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id]);

    $this->actingAs($owner);

    $response = json_decode((new UpdateTaskTool())->handle(
        app(IdCipher::class)->encrypt($task->getKey()),
        'Updated title',
    ), true);

    expect($response)->toHaveKey('task');
});

it('denies task updates to an outsider', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs(User::factory()->create());

    expect(fn () => (new UpdateTaskTool())->handle(
        app(IdCipher::class)->encrypt($task->getKey()),
        'Hijacked',
    ))->toThrow(AuthorizationException::class);
});

it('throws when the task key does not resolve to any task', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    expect(fn () => (new UpdateTaskTool())->handle('forged-token', 'Whatever'))->toThrow(ModelNotFoundException::class);
});

it('sends a task updated notification when the task changes', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id]);

    $this->actingAs($owner);

    (new UpdateTaskTool())->handle(app(IdCipher::class)->encrypt($task->getKey()), 'Updated title');

    Notification::assertSentTo($owner, TaskUpdatedNotification::class);
});

it('changes the task status through the MCP tool', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id, 'status' => TaskStatus::Todo]);

    $this->actingAs($owner);

    $response = json_decode((new UpdateTaskTool())->handle(
        app(IdCipher::class)->encrypt($task->getKey()),
        null,
        null,
        null,
        null,
        TaskStatus::InProgress,
        null,
    ), true);

    expect($response['task']['status'])->toBe(TaskStatus::InProgress->value);
});

it('validates task status transitions through the MCP tool', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id, 'status' => TaskStatus::Todo]);

    $this->actingAs($owner);

    expect(fn () => (new UpdateTaskTool())->handle(
        app(IdCipher::class)->encrypt($task->getKey()),
        null,
        null,
        null,
        null,
        TaskStatus::Done,
        null,
    ))->toThrow(ValidationException::class);
});

it('assigns a member to the task through the MCP tool', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
    $task = Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id]);

    $this->actingAs($owner);

    $response = json_decode((new UpdateTaskTool())->handle(
        app(IdCipher::class)->encrypt($task->getKey()),
        null,
        null,
        null,
        null,
        null,
        $member->id,
    ), true);

    expect($response['task']['assignee']['id'])->toBe($member->id);
});

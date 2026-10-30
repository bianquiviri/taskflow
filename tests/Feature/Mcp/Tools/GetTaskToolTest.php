<?php

declare(strict_types=1);

use App\Mcp\Tools\Tasks\GetTaskTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\IdCipher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('resolves a task from its encrypted key', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner);

    $response = json_decode((new GetTaskTool())->handle(app(IdCipher::class)->encrypt($task->getKey())), true);

    expect($response['task']['id'])->toBe($task->getKey());
});

it('throws when the encrypted key does not resolve to a task', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    expect(fn () => (new GetTaskTool())->handle('not-a-valid-token'))->toThrow(ModelNotFoundException::class);
});

it('refuses a task the acting user cannot view', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider);

    expect(fn () => (new GetTaskTool())->handle(app(IdCipher::class)->encrypt($task->getKey())))
        ->toThrow(AuthorizationException::class);
});

<?php

declare(strict_types=1);

use App\Mcp\Tools\Tasks\ListTasksByProjectTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\IdCipher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('lists the tasks of the project addressed by its encrypted key', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->for($project, 'project')->count(2)->create();

    $this->actingAs($owner);

    $response = json_decode((new ListTasksByProjectTool())->handle(app(IdCipher::class)->encrypt($project->getKey())), true);

    expect($response['tasks'])->toHaveCount(2);
});

it('throws when the encrypted key does not resolve to a project', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    expect(fn () => (new ListTasksByProjectTool())->handle('not-a-valid-token'))->toThrow(ModelNotFoundException::class);
});

it('refuses a project the acting user cannot view', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider);

    expect(fn () => (new ListTasksByProjectTool())->handle(app(IdCipher::class)->encrypt($project->getKey())))
        ->toThrow(AuthorizationException::class);
});

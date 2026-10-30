<?php

declare(strict_types=1);

use App\Mcp\Tools\Projects\GetProjectTool;
use App\Models\Project;
use App\Models\User;
use App\Support\IdCipher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('resolves a project from its encrypted key', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner);

    $response = json_decode((new GetProjectTool())->handle(app(IdCipher::class)->encrypt($project->getKey())), true);

    expect($response['project']['id'])->toBe($project->getKey());
});

it('throws when the encrypted key does not resolve to a project', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    expect(fn () => (new GetProjectTool())->handle('not-a-valid-token'))->toThrow(ModelNotFoundException::class);
});

it('refuses a project the acting user cannot view', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider);

    expect(fn () => (new GetProjectTool())->handle(app(IdCipher::class)->encrypt($project->getKey())))
        ->toThrow(AuthorizationException::class);
});

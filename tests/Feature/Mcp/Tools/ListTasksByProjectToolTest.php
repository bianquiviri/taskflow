<?php

declare(strict_types=1);

use App\Mcp\Tools\Tasks\ListTasksByProjectTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\IdCipher;

it('lists the tasks of the project addressed by its encrypted key', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->for($project, 'project')->count(2)->create();

    $this->actingAs($owner);

    $response = json_decode((new ListTasksByProjectTool())->handle(app(IdCipher::class)->encrypt($project->getKey())), true);

    expect($response['tasks'])->toHaveCount(2);
});

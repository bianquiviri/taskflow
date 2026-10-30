<?php

declare(strict_types=1);

use App\Mcp\Tools\Tasks\ListMyTasksTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\IdCipher;

it('lists the tasks assigned to the authenticated user', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id]);

    $this->actingAs($owner);

    $response = json_decode((new ListMyTasksTool())->handle(), true);

    expect($response['tasks'])->toHaveCount(1)
        ->and($response['tasks'][0]['key'])->toBe(app(IdCipher::class)->encrypt($task->getKey()));
});

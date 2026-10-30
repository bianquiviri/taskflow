<?php

declare(strict_types=1);

use App\Mcp\Tools\Tasks\ListMyTasksTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('lists the tasks assigned to the authenticated user', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->for($project, 'project')->create(['assignee_id' => $owner->id]);

    $this->actingAs($owner);

    $response = json_decode((new ListMyTasksTool())->handle(), true);

    expect($response['tasks'])->toHaveCount(1);
});

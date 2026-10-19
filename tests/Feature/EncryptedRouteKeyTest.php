<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Team;
use App\Models\User;
use App\Support\IdCipher;
use Illuminate\Support\Facades\Storage;

it('addresses a project by a token instead of its id', function () {
    $project = Project::factory()->create();
    $token = app(IdCipher::class)->encrypt($project->getKey());

    expect($project->getRouteKey())->toBe($token)
        ->and(route('projects.show', $project))->toEndWith('/projects/'.$token);
});

it('carries the token beside the id so a page can build its links', function () {
    $project = Project::factory()->create();

    expect($project->toArray())
        ->toHaveKey('id', $project->getKey())
        ->toHaveKey('key', $project->getRouteKey());
});

it('resolves a bound token back to the project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk();
});

it('resolves a bound token back to the task', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertOk();
});

it('resolves a bound token back to the team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('teams.show', $team))
        ->assertOk();
});

it('resolves a token nested under its parent', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertRedirect();

    $this->assertDatabaseMissing('task_files', ['id' => $file->getKey()]);
});

it('refuses a raw id where a token is expected', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get('/projects/'.$project->getKey())
        ->assertNotFound();
});

it('refuses a token it did not issue', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get('/projects/'.app(IdCipher::class)->encrypt($project->getKey()).'x')
        ->assertNotFound();
});

it('keeps a nested binding scoped to its own parent', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $otherTask = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($otherTask, 'task')->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertNotFound();
});

<?php

declare(strict_types=1);

use App\Mcp\Tools\Tasks\CreateTaskTool;
use App\Models\Project;
use App\Models\User;
use App\Notifications\TaskCreatedNotification;
use App\Support\IdCipher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;

it('creates a task in the project addressed by the encrypted key', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner);

    $response = json_decode((new CreateTaskTool())->handle(
        app(IdCipher::class)->encrypt($project->getKey()),
        'Write the MCP write tools',
    ), true);

    expect($response)->toHaveKey('task');
});

it('denies task creation to a plain project member', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $project->members()->create(['user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member);

    expect(fn () => (new CreateTaskTool())->handle(
        app(IdCipher::class)->encrypt($project->getKey()),
        'Should not create me',
    ))->toThrow(AuthorizationException::class);
});

it('throws when the project key does not resolve to any project', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    expect(fn () => (new CreateTaskTool())->handle('forged-token', 'Whatever'))->toThrow(ModelNotFoundException::class);
});

it('sends a task created notification to the project owner', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner);

    (new CreateTaskTool())->handle(app(IdCipher::class)->encrypt($project->getKey()), 'Notify the owner');

    Notification::assertSentTo($project->owner, TaskCreatedNotification::class);
});

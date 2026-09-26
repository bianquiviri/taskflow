<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets a project member view and create comments on every project task', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $comment = Comment::factory()->for($task, 'task')->for($member, 'user')->create();
    $gate = Gate::forUser($member);

    expect($gate->allows('viewAny', [Comment::class, $project]))->toBeTrue()
        ->and($gate->allows('view', $comment))->toBeTrue()
        ->and($gate->allows('create', [Comment::class, $task]))->toBeTrue();
});

it('lets the project owner view and create comments', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $comment = Comment::factory()->for($task, 'task')->for($owner, 'user')->create();
    $gate = Gate::forUser($owner);

    expect($gate->allows('viewAny', [Comment::class, $project]))->toBeTrue()
        ->and($gate->allows('view', $comment))->toBeTrue()
        ->and($gate->allows('create', [Comment::class, $task]))->toBeTrue();
});

it('forbids viewing and creating comments for an outsider', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $comment = Comment::factory()->for($task, 'task')->for($owner, 'user')->create();
    $gate = Gate::forUser($outsider);

    expect($gate->allows('viewAny', [Comment::class, $project]))->toBeFalse()
        ->and($gate->allows('view', $comment))->toBeFalse()
        ->and($gate->allows('create', [Comment::class, $task]))->toBeFalse();
});

it('lets only the author update and delete a comment', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $author->id, 'role' => ProjectRole::Member]);
    $project->members()->create(['user_id' => $other->id, 'role' => ProjectRole::Admin]);
    $task = Task::factory()->for($project, 'project')->create();
    $comment = Comment::factory()->for($task, 'task')->for($author, 'user')->create();

    expect(Gate::forUser($author)->allows('update', $comment))->toBeTrue()
        ->and(Gate::forUser($author)->allows('delete', $comment))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $comment))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $comment))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $comment))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $comment))->toBeFalse();
});

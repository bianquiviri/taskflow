<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets a project member view, download and create attachments', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();
    $gate = Gate::forUser($member);

    expect($gate->allows('view', $file))->toBeTrue()
        ->and($gate->allows('download', $file))->toBeTrue()
        ->and($gate->allows('create', [TaskFile::class, $task]))->toBeTrue();
});

it('lets the project owner view, download, create and remove any attachment', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->create();
    $gate = Gate::forUser($owner);

    expect($gate->allows('view', $file))->toBeTrue()
        ->and($gate->allows('download', $file))->toBeTrue()
        ->and($gate->allows('create', [TaskFile::class, $task]))->toBeTrue()
        ->and($gate->allows('delete', $file))->toBeTrue();
});

it('forbids an outsider from viewing, downloading, creating or removing attachments', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();
    $gate = Gate::forUser($outsider);

    expect($gate->allows('view', $file))->toBeFalse()
        ->and($gate->allows('download', $file))->toBeFalse()
        ->and($gate->allows('create', [TaskFile::class, $task]))->toBeFalse()
        ->and($gate->allows('delete', $file))->toBeFalse();
});

it('lets the uploader and a project admin remove an attachment', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $uploader = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $admin->id, 'role' => ProjectRole::Admin]);
    $project->members()->create(['user_id' => $uploader->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($uploader, 'user')->create();

    expect(Gate::forUser($uploader)->allows('delete', $file))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $file))->toBeTrue();
});

it('forbids a plain member from removing an attachment they did not upload', function () {
    $owner = User::factory()->create();
    $uploader = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $uploader->id, 'role' => ProjectRole::Member]);
    $project->members()->create(['user_id' => $other->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($uploader, 'user')->create();

    expect(Gate::forUser($other)->allows('delete', $file))->toBeFalse();
});

it('forbids an uploader who left the project from removing their own attachment', function () {
    $owner = User::factory()->create();
    $formerMember = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($formerMember, 'user')->create();

    expect(Gate::forUser($formerMember)->allows('delete', $file))->toBeFalse()
        ->and(Gate::forUser($formerMember)->allows('view', $file))->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets a member view all project tasks but only update assigned tasks', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $assigned = Task::factory()->for($project, 'project')->for($member, 'assignee')->create();
    $unassigned = Task::factory()->for($project, 'project')->create();
    $gate = Gate::forUser($member);

    expect($gate->allows('viewAny', [Task::class, $project]))->toBeTrue()
        ->and($gate->allows('create', [Task::class, $project]))->toBeFalse()
        ->and($gate->allows('view', $assigned))->toBeTrue()
        ->and($gate->allows('view', $unassigned))->toBeTrue()
        ->and($gate->allows('update', $assigned))->toBeTrue()
        ->and($gate->allows('changeStatus', $assigned))->toBeTrue()
        ->and($gate->allows('update', $unassigned))->toBeFalse()
        ->and($gate->allows('changeStatus', $unassigned))->toBeFalse()
        ->and($gate->allows('assign', $assigned))->toBeFalse();
});

it('lets a project owner manage every task', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $gate = Gate::forUser($owner);

    expect($gate->allows('viewAny', [Task::class, $project]))->toBeTrue()
        ->and($gate->allows('create', [Task::class, $project]))->toBeTrue()
        ->and($gate->allows('view', $task))->toBeTrue()
        ->and($gate->allows('update', $task))->toBeTrue()
        ->and($gate->allows('assign', $task))->toBeTrue()
        ->and($gate->allows('changeStatus', $task))->toBeTrue();
});

it('lets a project admin manage every task', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $admin->id, 'role' => ProjectRole::Admin]);
    $task = Task::factory()->for($project, 'project')->create();
    $gate = Gate::forUser($admin);

    expect($gate->allows('viewAny', [Task::class, $project]))->toBeTrue()
        ->and($gate->allows('create', [Task::class, $project]))->toBeTrue()
        ->and($gate->allows('view', $task))->toBeTrue()
        ->and($gate->allows('update', $task))->toBeTrue()
        ->and($gate->allows('assign', $task))->toBeTrue()
        ->and($gate->allows('changeStatus', $task))->toBeTrue();
});

it('forbids every task operation for an outsider', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $gate = Gate::forUser($outsider);

    expect($gate->allows('viewAny', [Task::class, $project]))->toBeFalse()
        ->and($gate->allows('create', [Task::class, $project]))->toBeFalse()
        ->and($gate->allows('view', $task))->toBeFalse()
        ->and($gate->allows('update', $task))->toBeFalse()
        ->and($gate->allows('assign', $task))->toBeFalse()
        ->and($gate->allows('changeStatus', $task))->toBeFalse();
});

it('enforces the policy through task routes', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $unassigned = Task::factory()->for($project, 'project')->create();

    $this->actingAs($member)
        ->get(route('projects.tasks.index', $project))
        ->assertOk();

    $this->actingAs($member)
        ->post(route('projects.tasks.store', $project), ['title' => 'Forbidden'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('tasks.update', $unassigned), ['title' => 'Forbidden'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('tasks.assign', $unassigned), ['assignee_id' => $member->id])
        ->assertForbidden();
});

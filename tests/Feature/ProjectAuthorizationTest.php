<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

it('lets any authenticated user create a project', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), ['name' => 'Anything'])
        ->assertRedirect();
});

it('forbids a non-member from viewing a project', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($outsider)
        ->get(route('projects.show', $project))
        ->assertForbidden();
});

it('forbids a non-member from updating or archiving a project', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($outsider)
        ->patch(route('projects.update', $project), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patch(route('projects.archive', $project))
        ->assertForbidden();
});

it('lets a member view but not update or archive a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertOk();

    $this->actingAs($member)
        ->patch(route('projects.update', $project), ['name' => 'Not Yours'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('projects.archive', $project))
        ->assertForbidden();
});

it('lets an admin member update and archive a project', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $admin->id, 'role' => ProjectRole::Admin]);

    $this->actingAs($admin)
        ->patch(route('projects.update', $project), ['name' => 'Renamed by Admin'])
        ->assertRedirect(route('projects.show', $project));

    $this->actingAs($admin)
        ->patch(route('projects.archive', $project))
        ->assertRedirect(route('projects.index'));
});

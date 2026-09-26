<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogService;

it('returns the subject activity newest first', function () {
    $actor = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->create();

    ActivityLog::forSubject($project)->delete();

    $oldest = ActivityLog::factory()->create([
        'actor_id' => $actor->id,
        'subject_type' => 'project',
        'subject_id' => $project->id,
        'event' => ActivityEvent::ProjectCreated,
        'created_at' => now()->subDays(2),
    ]);

    $newest = ActivityLog::factory()->create([
        'actor_id' => $actor->id,
        'subject_type' => 'project',
        'subject_id' => $project->id,
        'event' => ActivityEvent::ProjectArchived,
        'created_at' => now(),
    ]);

    $page = (new ActivityLogService())->forSubject($project, 10);

    expect($page->total())->toBe(2)
        ->and($page->first()->is($newest))->toBeTrue()
        ->and($page->first()->actor->is($actor))->toBeTrue()
        ->and($page->pluck('id')->all())->toBe([$newest->id, $oldest->id]);
});

it('only returns the entries of the requested subject', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    ActivityLog::forSubject($project)->create([
        'event' => ActivityEvent::ProjectUpdated,
        'subject_type' => 'project',
        'subject_id' => $project->id,
    ]);

    $service = new ActivityLogService();

    expect($service->forSubject($project)->total())->toBe(2)
        ->and($service->forSubject($otherProject)->total())->toBe(1);
});

it('paginates the subject activity', function () {
    $project = Project::factory()->create();

    ActivityLog::factory()->count(2)->create([
        'subject_type' => 'project',
        'subject_id' => $project->id,
    ]);

    $page = (new ActivityLogService())->forSubject($project, 2);

    expect($page->total())->toBe(3)
        ->and($page->count())->toBe(2)
        ->and($page->lastPage())->toBe(2);
});

it('exposes the project activity on the project page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $created = ActivityLog::forSubject($project)->sole();

    $updated = ActivityLog::factory()->create([
        'actor_id' => $user->id,
        'subject_type' => 'project',
        'subject_id' => $project->id,
        'event' => ActivityEvent::ProjectUpdated,
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Projects/Show')
                ->has('activity.data', 2)
                ->where('activity.data.0.id', $updated->id)
                ->where('activity.data.0.event', ActivityEvent::ProjectUpdated->value)
                ->where('activity.data.0.actor.name', $user->name)
                ->where('activity.data.1.id', $created->id)
                ->where('activity.total', 2),
        );
});

it('returns an empty activity page for a project without entries', function () {
    $project = Project::factory()->create();

    ActivityLog::forSubject($project)->delete();

    expect((new ActivityLogService())->forSubject($project)->total())->toBe(0);
});

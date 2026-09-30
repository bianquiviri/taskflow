<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityFeedService;
use Illuminate\Support\Carbon;

it('describes an entry with its actor, its action, its target and when it happened', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->actingAs($owner);
    $project = Project::factory()->for($owner, 'owner')->create(['name' => 'Website Redesign']);

    $created = ActivityLog::forSubject($project)->sole();

    $entry = (new ActivityFeedService())->forProject($project)->first();

    expect($entry['id'])->toBe($created->id)
        ->and($entry['event'])->toBe(ActivityEvent::ProjectCreated->value)
        ->and($entry['actor'])->toBe(['id' => $owner->id, 'name' => 'Ada Lovelace'])
        ->and($entry['label'])->toBe('created the project')
        ->and($entry['target'])->toBe([
            'type' => 'project',
            'id' => $project->id,
            'title' => 'Website Redesign',
            'url' => route('projects.show', $project),
        ])
        ->and($entry['icon'])->toBe('plus')
        ->and($entry['day'])->toBe(now()->toDateString())
        ->and(Carbon::parse($entry['at'])->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('reads the history of the project and of its tasks as a single feed', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['title' => 'Ship the release']);

    $entries = (new ActivityFeedService())->forProject($project)->items();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['event'])->toBe(ActivityEvent::TaskCreated->value)
        ->and($entries[0]['label'])->toBe('added the task')
        ->and($entries[0]['actor']['name'])->toBe($owner->name)
        ->and($entries[0]['target'])->toBe([
            'type' => 'task',
            'id' => $task->id,
            'title' => 'Ship the release',
            'url' => route('tasks.show', $task),
        ])
        ->and($entries[1]['event'])->toBe(ActivityEvent::ProjectCreated->value)
        ->and($entries[1]['target']['type'])->toBe('project');
});

it('names the destination status when a task moves', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['status' => TaskStatus::Todo]);

    $task->update(['status' => TaskStatus::InProgress]);

    $entry = (new ActivityFeedService())->forProject($project)->first();

    expect($entry['event'])->toBe(ActivityEvent::TaskStatusChanged->value)
        ->and($entry['label'])->toBe('moved the task to In Progress')
        ->and($entry['icon'])->toBe('check')
        ->and($entry['target']['title'])->toBe($task->title);
});

it('keeps the entries of other projects out of the feed', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $other = Project::factory()->for($owner, 'owner')->create();
    Task::factory()->for($other, 'project')->create();

    $feed = (new ActivityFeedService())->forProject($project);

    expect($feed->total())->toBe(2)
        ->and($feed->pluck('target.id')->all())->toBe([$task->id, $project->id]);
});

it('names an entry whose actor left', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create(['name' => 'Grace Hopper']);
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($colleague);
    Task::factory()->for($project, 'project')->create(['title' => 'Ship the release']);

    $colleague->delete();

    $entry = (new ActivityFeedService())->forProject($project)->first();

    expect($entry['actor'])->toBe(['id' => null, 'name' => 'Former member'])
        ->and($entry['label'])->toBe('added the task');
});

it('drops the history of a task that is no longer there', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create(['title' => 'Ship the release']);

    Task::withoutEvents(fn () => $task->delete());

    $entries = (new ActivityFeedService())->forProject($project)->items();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['event'])->toBe(ActivityEvent::ProjectCreated->value)
        ->and($entries[0]['target']['title'])->toBe($project->name)
        ->and($entries[0]['target']['url'])->toBe(route('projects.show', $project));
});

it('feeds a project without any entry nothing at all', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    ActivityLog::forSubject($project)->delete();

    $feed = (new ActivityFeedService())->forProject($project);

    expect($feed->total())->toBe(0)
        ->and($feed->items())->toBe([]);
});

it('pages the feed ten entries at a time', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    ActivityLog::forSubject($project)->delete();
    ActivityLog::factory()->count(10)->create([
        'subject_type' => (new Project())->getMorphClass(),
        'subject_id' => $project->id,
        'event' => ActivityEvent::ProjectUpdated,
    ]);

    $feed = (new ActivityFeedService())->forProject($project);

    expect($feed->total())->toBe(10)
        ->and($feed->count())->toBe(10)
        ->and($feed->lastPage())->toBe(1);
});

it('keeps the feed on its own page parameter, away from the tasks', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    ActivityLog::forSubject($project)->delete();
    ActivityLog::factory()->count(11)->create([
        'subject_type' => (new Project())->getMorphClass(),
        'subject_id' => $project->id,
        'event' => ActivityEvent::ProjectUpdated,
    ]);

    $this->actingAs($owner)
        ->get(route('projects.show', ['project' => $project, 'page' => 1, 'activity_page' => 2]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('activity.total', 11)
            ->where('activity.current_page', 2)
            ->where('activity.last_page', 2)
            ->has('activity.data', 1)
            ->where('tasks.current_page', 1),
        );
});

it('sends the project page the feed of the project and its tasks', function () {
    $owner = User::factory()->create(['name' => 'Grace Hopper']);
    $this->actingAs($owner);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('activity.data', 2)
            ->where('activity.data.0.actor.name', 'Grace Hopper')
            ->where('activity.data.0.target.id', $task->id)
            ->where('activity.data.0.target.url', route('tasks.show', $task))
            ->where('activity.data.0.day', now()->toDateString()),
        );
});

it('keeps the feed behind the project policy', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);

    $this->get(route('projects.show', $project))->assertRedirect(route('login'));

    $this->actingAs($outsider)
        ->get(route('projects.show', $project))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activity.data', 1));
});

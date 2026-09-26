<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('lets a project member comment on a task', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($member)
        ->post(route('comments.store', $task), ['body' => 'Ship it @nobody'])
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success', 'Comment added.');

    $this->assertDatabaseHas('comments', [
        'task_id' => $task->id,
        'user_id' => $member->id,
        'body' => 'Ship it @nobody',
    ]);
});

it('rejects an empty or overly long comment body', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->post(route('comments.store', $task), ['body' => '   '])
        ->assertSessionHasErrors('body');

    $this->actingAs($owner)
        ->post(route('comments.store', $task), ['body' => str_repeat('a', 5001)])
        ->assertSessionHasErrors('body');

    expect(Comment::query()->count())->toBe(0);
});

it('lists the comments of a task with their authors', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $first = Comment::factory()->for($task, 'task')->for($owner, 'user')->create(['body' => 'First']);
    $second = Comment::factory()->for($task, 'task')->for($owner, 'user')->create(['body' => 'Second']);

    $this->actingAs($owner)
        ->getJson(route('comments.index', $task))
        ->assertOk()
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.body', 'First')
        ->assertJsonPath('data.0.user.name', 'Ada Lovelace')
        ->assertJsonPath('data.1.id', $second->id);
});

it('forbids an outsider from listing or adding comments', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($outsider)
        ->getJson(route('comments.index', $task))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('comments.store', $task), ['body' => 'Sneaking in'])
        ->assertForbidden();

    expect(Comment::query()->count())->toBe(0);
});

it('renders the comments of a task in the task show page', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $comment = Comment::factory()->for($task, 'task')->for($owner, 'user')->create(['body' => 'Ready to ship']);

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Tasks/Show')
                ->where('comments.0.id', $comment->id)
                ->where('comments.0.body', 'Ready to ship')
                ->where('comments.0.user.name', 'Ada Lovelace')
                ->has('comments', 1),
        );
});

it('shows an empty comment list for a task without comments', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Tasks/Show')->has('comments', 0));
});

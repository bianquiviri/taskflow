<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('stores a comment with its author and task', function () {
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $task = Task::factory()->create();

    $comment = Comment::factory()->for($task, 'task')->for($author, 'user')->create([
        'body' => 'Please review this.',
    ]);

    expect($comment->refresh()->body)->toBe('Please review this.')
        ->and($comment->task->is($task))->toBeTrue()
        ->and($comment->user->is($author))->toBeTrue();
});

it('reads the comments of a task in chronological order', function () {
    $author = User::factory()->create();
    $task = Task::factory()->create();
    $first = Comment::factory()->for($task, 'task')->for($author, 'user')->create();
    $second = Comment::factory()->for($task, 'task')->for($author, 'user')->create();

    expect($task->comments()->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($author->comments()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('deletes every comment of a task when the task is deleted', function () {
    $task = Task::factory()->create();
    Comment::factory()->for($task, 'task')->create();

    $task->delete();

    expect(Comment::query()->count())->toBe(0);
});

it('builds a comment through the task relation', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project, 'project')->create();

    $comment = $task->comments()->create(['user_id' => $project->owner_id, 'body' => 'Looks good.']);

    expect($comment->exists)->toBeTrue()
        ->and($comment->task_id)->toBe($task->id);
});

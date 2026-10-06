<?php

declare(strict_types=1);

use App\Actions\AddCommentAction;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\YouWereMentioned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

it('creates a comment and notifies every mentioned project member', function () {
    Notification::fake();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $mentioned = User::factory()->create(['name' => 'Grace Hopper']);
    $project = Project::factory()->for($author, 'owner')->create();
    $project->members()->create(['user_id' => $mentioned->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();

    $comment = (new AddCommentAction())($task, $author, 'Could you review this @grace-hopper?');

    expect($comment->task_id)->toBe($task->id)
        ->and($comment->user_id)->toBe($author->id);

    Notification::assertSentTo($mentioned, YouWereMentioned::class, function (YouWereMentioned $notification) use ($comment): bool {
        return $notification->comment->is($comment)
            && $notification instanceof ShouldQueue
            && $notification->via($notification->comment->user) === ['database', 'mail'];
    });
});

it('notifies the project owner when they are mentioned', function () {
    Notification::fake();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $owner = User::factory()->create(['name' => 'Alan Turing']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $author->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();

    (new AddCommentAction())($task, $author, 'Nice work @alan-turing');

    Notification::assertSentTo($owner, YouWereMentioned::class);
    Notification::assertNotSentTo($author, YouWereMentioned::class);
});

it('does not notify the author for a self mention', function () {
    Notification::fake();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->for($author, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    (new AddCommentAction())($task, $author, 'Note to self @ada-lovelace');

    Notification::assertNothingSent();
});

it('ignores handles that are not project members', function () {
    Notification::fake();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $outsider = User::factory()->create(['name' => 'Grace Hopper']);
    $project = Project::factory()->for($author, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    (new AddCommentAction())($task, $author, 'Hello @grace-hopper and @nobody');

    Notification::assertNotSentTo($outsider, YouWereMentioned::class);
    Notification::assertNothingSent();
});

it('stores a database notification for the mentioned user', function () {
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $mentioned = User::factory()->create(['name' => 'Grace Hopper']);
    $project = Project::factory()->for($author, 'owner')->create();
    $project->members()->create(['user_id' => $mentioned->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create(['title' => 'Ship the release']);

    (new AddCommentAction())($task, $author, 'Please review @grace-hopper');

    $notification = $mentioned->notifications()->sole();

    expect($notification->type)->toBe(YouWereMentioned::class)
        ->and($notification->data)->toMatchArray([
            'comment_id' => $task->comments()->sole()->id,
            'task_id' => $task->id,
        ])
        ->and($notification->data['message'])->toContain('Ada Lovelace');
});

it('builds a mention mail message pointing at the task', function () {
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $mentioned = User::factory()->create(['name' => 'Grace Hopper']);
    $project = Project::factory()->for($author, 'owner')->create();
    $project->members()->create(['user_id' => $mentioned->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create(['title' => 'Ship the release']);
    $comment = (new AddCommentAction())($task, $author, 'Please review @grace-hopper');

    $mail = (new YouWereMentioned($comment))->toMail($mentioned);

    expect($mail->subject)->toBe('You were mentioned in "Ship the release"')
        ->and(implode("\n", array_map(strval(...), $mail->introLines)))->toContain('Ada Lovelace');
});

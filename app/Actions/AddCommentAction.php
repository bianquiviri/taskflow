<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\YouWereMentioned;
use App\Support\MentionParser;

final readonly class AddCommentAction
{
    public function __invoke(Task $task, User $author, string $body): Comment
    {
        $comment = $task->comments()->create([
            'user_id' => $author->getKey(),
            'body' => $body,
        ]);

        $this->notifyMentions($task, $comment, $author);

        return $comment;
    }

    private function notifyMentions(Task $task, Comment $comment, User $author): void
    {
        $handles = MentionParser::handles($comment->body);

        if ($handles === []) {
            return;
        }

        $project = $task->project;
        $candidates = $project->members()->with('user')->get()->pluck('user')
            ->push($project->owner)
            ->filter();

        foreach ($candidates as $candidate) {
            if ($candidate->is($author) || ! in_array(MentionParser::handleFor($candidate->name), $handles, true)) {
                continue;
            }

            $candidate->notify(new YouWereMentioned($comment));
        }
    }
}

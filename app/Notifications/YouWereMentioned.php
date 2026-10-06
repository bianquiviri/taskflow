<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class YouWereMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Comment $comment)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $comment = $this->comment;

        return (new MailMessage())
            ->subject(sprintf('You were mentioned in "%s"', $comment->task->title))
            ->line(sprintf('%s mentioned you in a comment:', $comment->user->name))
            ->line($comment->body)
            ->action('Open the task', route('tasks.show', $comment->task));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->getKey(),
            'task_id' => $this->comment->task_id,
            'message' => sprintf('%s mentioned you in a comment.', $this->comment->user->name),
        ];
    }
}

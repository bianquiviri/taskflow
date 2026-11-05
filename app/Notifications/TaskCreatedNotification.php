<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TaskCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Task $task)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(sprintf('Task "%s" was created', $this->task->title))
            ->action('Open the task', route('tasks.show', $this->task));
    }
}

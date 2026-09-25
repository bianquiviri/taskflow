<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;

it('queues mail notifications instead of sending them synchronously', function () {
    Queue::fake();

    NotificationFacade::route('mail', 'dev@taskflow.test')
        ->notify(new QueuedMailNotification());

    Queue::assertPushed(SendQueuedNotifications::class);
});

it('keeps redis as the default queue connection for the local stack', function () {
    $queueConnection = collect(file(base_path('.env.example')))
        ->map(fn (string $line): string => trim($line))
        ->first(fn (string $line): bool => str_starts_with($line, 'QUEUE_CONNECTION'));

    expect($queueConnection)->toBe('QUEUE_CONNECTION=redis');
});

class QueuedMailNotification extends Notification implements ShouldQueue
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->line('Delivered asynchronously by the queue worker.');
    }
}

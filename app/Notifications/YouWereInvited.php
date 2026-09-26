<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class YouWereInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TeamInvitation $invitation,
        public readonly string $token,
    ) {
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
        $team = $this->invitation->team;

        return (new MailMessage())
            ->subject(sprintf('You have been invited to join "%s"', $team->name))
            ->line(sprintf('%s invited you to join the "%s" team on TaskFlow as a %s.', $team->owner->name, $team->name, $this->invitation->role->label()))
            ->line('This invitation expires on '.$this->invitation->expires_at->translatedFormat('j F Y').'.')
            ->action('Accept invitation', route('team-invitations.accept', $this->token))
            ->line('If you were not expecting this invitation you can simply ignore this email.');
    }
}

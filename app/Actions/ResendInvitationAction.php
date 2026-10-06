<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\TeamInvitation;
use App\Notifications\YouWereInvited;
use App\Services\TeamInvitationService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final readonly class ResendInvitationAction
{
    public function __construct(
        private TeamInvitationService $invitations,
        private LogActivityAction $logActivity,
    ) {
    }

    public function __invoke(TeamInvitation $invitation): TeamInvitation
    {
        if ($invitation->email === null) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has no address to send it to.',
            ]);
        }

        $issued = $this->invitations->rotate($invitation);

        Notification::route('mail', $invitation->email)->notify(new YouWereInvited($issued->invitation, $issued->token));

        ($this->logActivity)(ActivityEvent::InvitationResent, $invitation->team, [
            'invitation_id' => $invitation->getKey(),
            'email' => $invitation->email,
            'role' => $invitation->role->value,
        ]);

        return $issued->invitation;
    }
}

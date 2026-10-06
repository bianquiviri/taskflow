<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\TeamInvitation;

/**
 * The only moment the raw invitation token exists: it is handed to the mail
 * notification and never stored.
 */
final readonly class IssuedTeamInvitation
{
    public function __construct(
        public TeamInvitation $invitation,
        public string $token,
    ) {
    }
}

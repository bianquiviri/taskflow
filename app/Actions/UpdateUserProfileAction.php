<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/**
 * Applies a profile edit to a user.
 *
 * A changed email address is stored straight away and the account goes back to
 * the pending verification state Laravel uses everywhere else: the timestamp is
 * cleared and a fresh verification link is sent to the address on file. The
 * user has to confirm it before the account can reach the rest of the
 * application, and can correct the address from the profile page meanwhile.
 */
final readonly class UpdateUserProfileAction
{
    public function __invoke(User $user, string $name, string $email): User
    {
        $user->name = $name;
        $user->email = $email;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}

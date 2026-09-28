<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Replaces the password of a user once the current one has been confirmed.
 *
 * The remember token is rotated so devices that rely on it are signed out of
 * the account, the way the password reset flow does it.
 */
final readonly class UpdateUserPasswordAction
{
    public function __invoke(User $user, #[\SensitiveParameter] string $password): User
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        return $user;
    }
}

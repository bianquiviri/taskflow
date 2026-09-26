<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Theme;
use App\Models\User;

final readonly class UpdateUserThemeAction
{
    public function __invoke(User $user, Theme $theme): User
    {
        $user->update(['theme' => $theme]);

        return $user;
    }
}

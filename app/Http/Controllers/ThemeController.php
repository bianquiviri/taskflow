<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserThemeAction;
use App\Enums\Theme;
use App\Http\Requests\UpdateThemeRequest;
use Illuminate\Http\RedirectResponse;

class ThemeController extends Controller
{
    public function update(UpdateThemeRequest $request, UpdateUserThemeAction $updateUserTheme): RedirectResponse
    {
        $updateUserTheme($request->user(), Theme::from($request->validated('theme')));

        return back();
    }
}

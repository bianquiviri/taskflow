<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends a team mutation back to the team surface the request came from: the
 * settings page keeps the manager in place, everything else falls back to the
 * team overview.
 */
final class TeamRedirector
{
    public function to(Team $team, Request $request): RedirectResponse
    {
        $settings = route('teams.settings', $team);

        return $request->headers->get('referer') === $settings
            ? redirect()->to($settings)
            : redirect()->route('teams.show', $team);
    }
}

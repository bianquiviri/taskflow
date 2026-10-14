<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AppInfoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(Request $request, AppInfoService $appInfo): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Welcome', [
            'appName' => config('app.name'),
            'version' => $appInfo->version(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AppInfoService;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(AppInfoService $appInfo): Response
    {
        return Inertia::render('Welcome', [
            'appName' => config('app.name'),
            'version' => $appInfo->version(),
        ]);
    }
}

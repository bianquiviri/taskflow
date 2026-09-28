<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
    ) {
    }

    /**
     * The landing page of a signed in user: the counters, the status
     * distribution and the work waiting on them, all scoped to their own
     * projects.
     */
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $metrics = $this->metrics->overview($request->user());

        return Inertia::render('Dashboard/Index', [
            'kpis' => $metrics['kpis'],
            'statusBreakdown' => $metrics['statusBreakdown'],
            'recentProjects' => $metrics['recentProjects'],
            'openTasks' => $metrics['openTasks'],
        ]);
    }
}

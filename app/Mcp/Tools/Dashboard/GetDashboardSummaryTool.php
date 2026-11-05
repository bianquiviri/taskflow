<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Dashboard;

use App\Mcp\Support\McpResponse;
use App\Models\Project;
use App\Services\DashboardMetricsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpMcp\Server\Attributes\McpTool;

final class GetDashboardSummaryTool
{
    #[McpTool(name: 'taskflow.dashboard.summary', description: 'Get dashboard KPI summary for the authenticated user.')]
    public function handle(): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        Gate::authorize('viewAny', Project::class);

        $service = app(DashboardMetricsService::class);
        $metrics = $service->overview($user);

        return McpResponse::json([
            'summary' => $metrics,
        ]);
    }
}

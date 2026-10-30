<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Projects;

use App\Mcp\Support\McpResponse;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpMcp\Server\Attributes\McpTool;

final class GetProjectTool
{
    #[McpTool(name: 'taskflow.projects.get', description: 'Get project details by its encrypted key.')]
    public function handle(string $projectKey): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        $project = Project::whereKey($projectKey)->firstOrFail();

        Gate::authorize('view', $project);

        $project->loadCount('tasks');

        return McpResponse::json([
            'project' => [
                'key' => $project->getRouteKey(),
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'slug' => $project->slug,
                'archived_at' => $project->archived_at?->toISOString(),
                'tasks_count' => $project->tasks_count,
            ],
        ]);
    }
}

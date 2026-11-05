<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Projects;

use App\Mcp\Support\McpResponse;
use App\Models\Project;
use App\Support\IdCipher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
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

        $projectId = app(IdCipher::class)->decrypt($projectKey);
        $project = $projectId === null ? null : Project::findOrFail($projectId);

        if ($project === null) {
            throw (new ModelNotFoundException())->setModel(Project::class);
        }

        Gate::authorize('view', $project);

        /** @var Carbon|null $archivedAt */
        $archivedAt = $project->archived_at;

        $project->loadCount('tasks');

        return McpResponse::json([
            'project' => [
                'key' => $project->getRouteKey(),
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'slug' => $project->slug,
                'archived_at' => $archivedAt?->toISOString(),
                'tasks_count' => $project->tasks_count,
            ],
        ]);
    }
}

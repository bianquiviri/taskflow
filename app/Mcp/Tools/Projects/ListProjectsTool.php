<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Projects;

use App\Mcp\Support\McpResponse;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpMcp\Server\Attributes\McpTool;

final class ListProjectsTool
{
    #[McpTool(name: 'taskflow.projects.list', description: 'List projects accessible to the authenticated user.')]
    public function handle(?bool $archived = false, ?string $search = null, ?int $limit = 50): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        $query = Project::query()
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        if ($archived === true) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query
            ->orderBy('name')
            ->limit(max(1, min($limit ?? 50, 100)))
            ->get();

        $items = $projects->map(function (Project $project): array {
            /** @var Carbon|null $archivedAt */
            $archivedAt = $project->archived_at;

            return [
                'key' => $project->getRouteKey(),
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'slug' => $project->slug,
                'archived_at' => $archivedAt?->toISOString(),
            ];
        })->all();

        return McpResponse::json([
            'projects' => $items,
            'count' => count($items),
        ]);
    }
}

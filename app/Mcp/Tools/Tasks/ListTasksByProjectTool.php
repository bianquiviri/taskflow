<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpResponse;
use App\Models\Project;
use App\Models\Task;
use App\Support\IdCipher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpMcp\Server\Attributes\McpTool;

final class ListTasksByProjectTool
{
    #[McpTool(name: 'taskflow.tasks.by_project', description: 'List tasks for a project the authenticated user can access.')]
    public function handle(string $projectKey, ?array $status = null, ?int $assigneeId = null, ?string $search = null, ?int $limit = 50): string
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

        $query = Task::query()
            ->where('project_id', $project->id)
            ->with('assignee:id,name,email');

        if (is_array($status) && $status !== []) {
            $query->whereIn('status', $status);
        }

        if (is_int($assigneeId)) {
            $query->where('assignee_id', $assigneeId);
        }

        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tasks = $query
            ->orderBy('position')
            ->orderBy('id')
            ->limit(max(1, min($limit ?? 50, 100)))
            ->get();

        $items = $tasks->map(function (Task $task): array {
            /** @var TaskStatus $status */
            $status = $task->status;

            /** @var TaskPriority $priority */
            $priority = $task->priority;

            /** @var Carbon|null $dueDate */
            $dueDate = $task->due_date;

            return [
                'key' => $task->getRouteKey(),
                'id' => $task->id,
                'title' => $task->title,
                'status' => $status->value,
                'priority' => $priority->value,
                'due_date' => $dueDate?->toDateString(),
                'assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                    'email' => $task->assignee->email,
                ] : null,
            ];
        })->all();

        return McpResponse::json([
            'project' => [
                'key' => $project->getRouteKey(),
                'id' => $project->id,
                'name' => $project->name,
            ],
            'tasks' => $items,
            'count' => count($items),
        ]);
    }
}

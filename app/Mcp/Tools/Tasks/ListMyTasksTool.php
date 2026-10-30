<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpResponse;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpMcp\Server\Attributes\McpTool;

final class ListMyTasksTool
{
    #[McpTool(name: 'taskflow.tasks.mine', description: 'List tasks assigned to the authenticated user.')]
    public function handle(?array $status = null, ?array $priority = null, ?string $due = null, ?int $limit = 50): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        $query = Task::query()
            ->where('assignee_id', $user->id)
            ->with(['project:id,name']);

        if (is_array($status) && $status !== []) {
            $query->whereIn('status', $status);
        }

        if (is_array($priority) && $priority !== []) {
            $query->whereIn('priority', $priority);
        }

        if ($due === 'overdue') {
            $query->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString());
        } elseif ($due === 'today') {
            $query->whereDate('due_date', now()->toDateString());
        } elseif ($due === 'upcoming') {
            $query->whereNotNull('due_date')->whereDate('due_date', '>', now()->toDateString());
        }

        $tasks = $query
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('position')
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
                'project' => $task->project ? [
                    'key' => $task->project->getRouteKey(),
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
            ];
        })->all();

        return McpResponse::json([
            'tasks' => $items,
            'count' => count($items),
        ]);
    }
}

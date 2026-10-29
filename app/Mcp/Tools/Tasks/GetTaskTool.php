<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Tasks;

use App\Mcp\Support\McpResponse;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpMcp\Server\Attributes\McpTool;

final class GetTaskTool
{
    #[McpTool(name: 'taskflow.tasks.get', description: 'Get task details by its encrypted key.')]
    public function handle(string $taskKey): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        $task = Task::whereKey($taskKey)
            ->with(['project', 'assignee', 'creator'])
            ->firstOrFail();

        Gate::authorize('view', $task);

        return McpResponse::json([
            'task' => [
                'key' => $task->getRouteKey(),
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status instanceof \UnitEnum ? $task->status->value : $task->status,
                'priority' => $task->priority instanceof \UnitEnum ? $task->priority->value : $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'position' => $task->position,
                'project' => $task->project ? [
                    'key' => $task->project->getRouteKey(),
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
                'assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                    'email' => $task->assignee->email,
                ] : null,
                'creator' => $task->creator ? [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name,
                    'email' => $task->creator->email,
                ] : null,
                'created_at' => $task->created_at?->toISOString(),
                'updated_at' => $task->updated_at?->toISOString(),
            ],
        ]);
    }
}

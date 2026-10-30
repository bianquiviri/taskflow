<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpResponse;
use App\Models\Task;
use App\Support\IdCipher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
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

        $taskId = app(IdCipher::class)->decrypt($taskKey);
        $task = $taskId === null ? null : Task::with(['project', 'assignee'])->findOrFail($taskId);

        if ($task === null) {
            throw (new ModelNotFoundException())->setModel(Task::class);
        }

        Gate::authorize('view', $task);

        /** @var TaskStatus $status */
        $status = $task->status;

        /** @var TaskPriority $priority */
        $priority = $task->priority;

        /** @var Carbon|null $dueDate */
        $dueDate = $task->due_date;

        return McpResponse::json([
            'task' => [
                'key' => $task->getRouteKey(),
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $status->value,
                'priority' => $priority->value,
                'due_date' => $dueDate?->toDateString(),
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
                'created_at' => $task->created_at?->toISOString(),
                'updated_at' => $task->updated_at?->toISOString(),
            ],
        ]);
    }
}

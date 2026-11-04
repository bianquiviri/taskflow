<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Tasks;

use App\Actions\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpResponse;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCreatedNotification;
use App\Support\IdCipher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpMcp\Server\Attributes\McpTool;

final class CreateTaskTool
{
    #[McpTool(name: 'taskflow.tasks.create', description: 'Create a task in a project.')]
    public function handle(string $projectKey, string $title, ?string $description = null, ?TaskPriority $priority = null, ?string $dueDate = null): string
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

        Gate::authorize('create', [Task::class, $project]);

        $task = app(CreateTaskAction::class)($project, [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'due_date' => $dueDate,
        ]);

        $owner = User::findOrFail((int) $project->owner_id);

        $owner->notify(new TaskCreatedNotification($task));

        /** @var TaskStatus $status */
        $status = $task->status;

        /** @var TaskPriority $priorityEnum */
        $priorityEnum = $task->priority;

        /** @var \Illuminate\Support\Carbon|null $dueDateEnum */
        $dueDateEnum = $task->due_date;

        return McpResponse::json([
            'task' => [
                'key' => $task->getRouteKey(),
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $status->value,
                'priority' => $priorityEnum->value,
                'due_date' => $dueDateEnum?->toDateString(),
                'position' => $task->position,
                'project' => [
                    'key' => $project->getRouteKey(),
                    'id' => $project->id,
                    'name' => $project->name,
                ],
                'assignee' => null,
                'created_at' => $task->created_at?->toISOString(),
                'updated_at' => $task->updated_at?->toISOString(),
            ],
        ]);
    }
}

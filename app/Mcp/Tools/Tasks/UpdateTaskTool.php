<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Tasks;

use App\Actions\AssignTaskAction;
use App\Actions\ChangeTaskStatusAction;
use App\Actions\UpdateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpResponse;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdatedNotification;
use App\Support\IdCipher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpMcp\Server\Attributes\McpTool;

final class UpdateTaskTool
{
    #[McpTool(name: 'taskflow.tasks.update', description: 'Update a task, including its status transitions or assignee.')]
    public function handle(
        string $taskKey,
        ?string $title = null,
        ?string $description = null,
        ?TaskPriority $priority = null,
        ?string $dueDate = null,
        ?TaskStatus $status = null,
        ?int $assigneeId = null,
    ): string {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        $taskId = app(IdCipher::class)->decrypt($taskKey);
        $task = $taskId === null ? null : Task::with(['project', 'assignee'])->findOrFail($taskId);

        if ($task === null) {
            throw (new ModelNotFoundException())->setModel(Task::class);
        }

        Gate::authorize('update', $task);

        $changed = false;

        if ($title !== null || $description !== null || $priority !== null || $dueDate !== null) {
            $data = [];

            if ($title !== null) {
                $data['title'] = $title;
            }

            if ($description !== null) {
                $data['description'] = $description;
            }

            if ($priority !== null) {
                $data['priority'] = $priority;
            }

            if ($dueDate !== null) {
                $data['due_date'] = $dueDate;
            }

            app(UpdateTaskAction::class)($task, $data);

            $changed = true;
        }

        if ($status !== null) {
            Gate::authorize('changeStatus', $task);

            app(ChangeTaskStatusAction::class)($task, $status);

            $changed = true;
        }

        if ($assigneeId !== null) {
            Gate::authorize('assign', $task);

            $assignee = User::findOrFail((int) $assigneeId);
            app(AssignTaskAction::class)($task, $assignee);
            $task->setRelation('assignee', $assignee);

            $changed = true;
        }

        if ($changed) {
            $notifiable = $task->assignee_id !== null
                ? User::findOrFail((int) $task->assignee_id)
                : User::findOrFail((int) $task->project->owner_id);

            $notifiable->notify(new TaskUpdatedNotification($task));
        }

        /** @var TaskStatus $taskStatus */
        $taskStatus = $task->status;

        /** @var TaskPriority $taskPriority */
        $taskPriority = $task->priority;

        /** @var \Illuminate\Support\Carbon|null $taskDueDate */
        $taskDueDate = $task->due_date;

        return McpResponse::json([
            'task' => [
                'key' => $task->getRouteKey(),
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $taskStatus->value,
                'priority' => $taskPriority->value,
                'due_date' => $taskDueDate?->toDateString(),
                'position' => $task->position,
                'project' => [
                    'key' => $task->project->getRouteKey(),
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ],
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

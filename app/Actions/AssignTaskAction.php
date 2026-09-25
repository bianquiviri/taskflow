<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class AssignTaskAction
{
    public function __invoke(Task $task, ?User $assignee): Task
    {
        if ($assignee !== null && ! $this->belongsToProject($task, $assignee)) {
            throw ValidationException::withMessages([
                'assignee_id' => 'The assignee must be a member of the project.',
            ]);
        }

        $task->update(['assignee_id' => $assignee?->getKey()]);

        return $task;
    }

    private function belongsToProject(Task $task, User $user): bool
    {
        $project = $task->project;

        return $project->owner_id === $user->getKey()
            || $project->members()->where('user_id', $user->getKey())->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

final readonly class ChangeTaskStatusAction
{
    public function __invoke(Task $task, TaskStatus $status): Task
    {
        if (! $task->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition a task from {$task->status->label()} to {$status->label()}.",
            ]);
        }

        $task->update(['status' => $status]);

        return $task;
    }
}

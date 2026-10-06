<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Task;

final readonly class UpdateTaskAction
{
    public function __invoke(Task $task, array $data): Task
    {
        $task->update($data);

        return $task;
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\LogActivityAction;
use App\Enums\ActivityEvent;
use App\Models\Task;
use Illuminate\Support\Arr;

final readonly class TaskObserver
{
    public function __construct(private LogActivityAction $logActivity)
    {
    }

    public function created(Task $task): void
    {
        ($this->logActivity)(ActivityEvent::TaskCreated, $task, [
            'title' => $task->title,
            'status' => $task->status->value,
        ]);
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            ($this->logActivity)(ActivityEvent::TaskStatusChanged, $task, [
                'from' => $task->getOriginal('status'),
                'to' => $task->status->value,
            ]);

            return;
        }

        ($this->logActivity)(ActivityEvent::TaskUpdated, $task, [
            'changes' => Arr::except($task->getChanges(), ['updated_at']),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TaskOrderService
{
    public function reorder(Project $project, array $orderedTaskIds): void
    {
        DB::transaction(function () use ($project, $orderedTaskIds): void {
            $ids = array_map(static fn (int|string $id): int => $id, array_values($orderedTaskIds));
            $projectTaskIds = Task::forProject($project)
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn (int $id): int => $id)
                ->all();

            if (! $this->isCompleteOrder($ids, $projectTaskIds)) {
                throw ValidationException::withMessages([
                    'task_ids' => 'The task order must contain every task in the project exactly once.',
                ]);
            }

            foreach ($ids as $position => $taskId) {
                Task::query()->whereKey($taskId)->update(['position' => $position + 1]);
            }
        });
    }

    private function isCompleteOrder(array $orderedTaskIds, array $projectTaskIds): bool
    {
        return count($orderedTaskIds) === count(array_unique($orderedTaskIds))
            && count($orderedTaskIds) === count($projectTaskIds)
            && array_diff($orderedTaskIds, $projectTaskIds) === []
            && array_diff($projectTaskIds, $orderedTaskIds) === [];
    }
}

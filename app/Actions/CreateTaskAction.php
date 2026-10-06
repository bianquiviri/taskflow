<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

final readonly class CreateTaskAction
{
    public function __invoke(Project $project, array $data): Task
    {
        return DB::transaction(function () use ($project, $data): Task {
            $position = ((int) Task::forProject($project)->max('position')) + 1;

            return Task::create([
                'project_id' => $project->getKey(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => TaskStatus::Todo,
                'priority' => $data['priority'] ?? TaskPriority::Medium,
                'due_date' => $data['due_date'] ?? null,
                'position' => $position,
            ]);
        });
    }
}

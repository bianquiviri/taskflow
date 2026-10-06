<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * How the task listings are queried: the filters a project board and a
 * "my tasks" view apply, and the values their filter forms offer.
 */
final readonly class TaskQueryService
{
    public const int PER_PAGE = 15;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function forProject(Project $project, array $filters): LengthAwarePaginator
    {
        return TaskQuery::make()
            ->forProject($project)
            ->apply($filters)
            ->orderByPosition()
            ->paginate(self::PER_PAGE);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function forAssignee(User $user, array $filters): LengthAwarePaginator
    {
        return TaskQuery::make()
            ->forProjectsOf($user)
            ->assignedTo($user)
            ->apply($filters)
            ->orderByDueDate()
            ->paginate(self::PER_PAGE);
    }

    /**
     * The values a listing's filter form offers. Assignees are only known for
     * a project listing; a "my tasks" view has no assignee to choose from.
     *
     * @return array{statuses: list<array{value: string, label: string}>, priorities: list<array{value: string, label: string}>, assignees: list<array{id: int, name: string}>}
     */
    public function filterOptions(?Project $project = null): array
    {
        return [
            'statuses' => $this->enumOptions(TaskStatus::cases()),
            'priorities' => $this->enumOptions(TaskPriority::cases()),
            'assignees' => $project === null ? [] : $this->assigneeOptions($project),
        ];
    }

    /**
     * @param  list<TaskStatus|TaskPriority>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            static fn (TaskStatus|TaskPriority $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function assigneeOptions(Project $project): array
    {
        return User::query()
            ->whereKey($project->owner_id)
            ->orWhereIn('id', $project->members()->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}

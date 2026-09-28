<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The read model of the project detail page: how far the project is along, who
 * works on it and what the signed in user may do with the tasks on screen.
 */
final readonly class ProjectOverviewService
{
    /**
     * Completion of a project, derived from the status of its tasks: a task
     * counts as done only when it is in the done status, and a project without
     * tasks sits at zero rather than at one hundred percent.
     *
     * @return array{total: int, done: int, percent: int}
     */
    public function progress(Project $project): array
    {
        $total = Task::forProject($project)->count();
        $done = Task::forProject($project)->withStatus(TaskStatus::Done)->count();

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total === 0 ? 0 : (int) round(100 * $done / $total),
        ];
    }

    /**
     * The people with access to the project by name, the owner carrying the
     * owner role whether or not it holds a membership row.
     *
     * @return list<array{id: int, name: string, role: string}>
     */
    public function people(Project $project): array
    {
        $rows = DB::table('users')
            ->leftJoin('project_members', 'project_members.user_id', '=', 'users.id')
            ->where(function (Builder $query) use ($project): void {
                $query->where('users.id', $project->owner_id)
                    ->orWhere('project_members.project_id', $project->getKey());
            })
            ->orderBy('users.name')
            ->get(['users.id as id', 'users.name as name', 'project_members.role as role'])
            ->map(static fn (object $row): array => (array) $row);

        $people = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            $people[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'role' => $id === $project->owner_id
                    ? ProjectRole::Owner->value
                    : (string) $row['role'],
            ];
        }

        return $people;
    }

    /**
     * The abilities the project page needs, as names to merge with the global
     * auth context. The project half of the task policy is resolved once and
     * then applied to every task on screen: a project manager may change any
     * task, a member only the tasks assigned to them.
     *
     * @param  array<array-key, Task>  $tasks
     * @return list<string>
     */
    public function permissions(Project $project, User $user, array $tasks): array
    {
        $managesTasks = Gate::forUser($user)->allows('create', [Task::class, $project]);

        $permissions = ['tasks.create' => $managesTasks];

        foreach ($tasks as $task) {
            $mayChange = $managesTasks || $task->assignee_id === $user->getKey();

            $permissions["tasks.{$task->getKey()}.changeStatus"] = $mayChange;
            $permissions["tasks.{$task->getKey()}.update"] = $mayChange;
        }

        return array_keys(array_filter($permissions));
    }
}

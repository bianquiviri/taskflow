<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The read model of the dashboard: the handful of numbers that answer "how is
 * my work going" without paging through a project. Everything is scoped to the
 * projects the user owns or belongs to, and the whole summary costs five
 * queries however many projects and tasks they have.
 */
final readonly class DashboardMetricsService
{
    public const int RECENT_PROJECTS_LIMIT = 5;

    public const int OPEN_TASKS_LIMIT = 8;

    /**
     * The whole dashboard: the KPI counters, the distribution behind the bar
     * chart, the projects worth opening and the tasks waiting on the user.
     *
     * @return array{kpis: array{projects: int, open: int, overdue: int, done: int, total: int}, statusBreakdown: list<array{value: string, label: string, count: int}>, recentProjects: list<array{id: int, name: string, slug: string, tasks: int, open: int}>, openTasks: list<array{id: int, title: string, status: string, priority: string, due_date: string|null, overdue: bool, project: array{id: int, name: string}}>}
     */
    public function overview(User $user): array
    {
        $breakdown = $this->statusBreakdown($user);
        $totals = $this->totals($breakdown);

        return [
            'kpis' => [
                'projects' => Project::query()->forUser($user)->active()->count(),
                'open' => $totals['open'],
                'overdue' => $this->overdueCount($user),
                'done' => $totals['done'],
                'total' => $totals['total'],
            ],
            'statusBreakdown' => $breakdown,
            'recentProjects' => $this->recentProjects($user),
            'openTasks' => $this->openTasks($user),
        ];
    }

    /**
     * How many tasks sit in each status, every status included so the chart
     * keeps the same columns whatever the user has been doing. The labels come
     * from the enum, the only place a status is named.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    public function statusBreakdown(User $user): array
    {
        $counts = $this->forUser($user)
            ->reorder()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map(
            static fn (TaskStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ],
            TaskStatus::cases(),
        );
    }

    /**
     * The most recently created active projects, each with how much of its work
     * is still open. The counts are read through the model rather than as
     * properties because they only exist on a query carrying the sub-selects.
     *
     * @return list<array{id: int, name: string, slug: string, tasks: int, open: int}>
     */
    public function recentProjects(User $user): array
    {
        return Project::query()
            ->forUser($user)
            ->active()
            ->latest()
            ->withCount([
                'tasks',
                'tasks as open_tasks_count' => fn (Builder $query): Builder => $query->whereNotIn('status', self::closedStatuses()),
            ])
            ->limit(self::RECENT_PROJECTS_LIMIT)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->getKey(),
                'name' => $project->name,
                'slug' => $project->slug,
                'tasks' => (int) $project->getAttribute('tasks_count'),
                'open' => (int) $project->getAttribute('open_tasks_count'),
            ])
            ->all();
    }

    /**
     * The tasks still to do, soonest due date first and undated ones last, so
     * the list opens on whatever is already late. Read as plain rows: a summary
     * never needs a hydrated model, and the date is compared here rather than
     * in the browser.
     *
     * @return list<array{id: int, title: string, status: string, priority: string, due_date: string|null, overdue: bool, project: array{id: int, name: string}}>
     */
    public function openTasks(User $user): array
    {
        $rows = DB::table('tasks')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereIn('tasks.project_id', $this->projectIds($user))
            ->whereNotIn('tasks.status', self::closedStatuses())
            ->orderByRaw('tasks.due_date is null, tasks.due_date asc')
            ->orderBy('tasks.id')
            ->limit(self::OPEN_TASKS_LIMIT)
            ->get([
                'tasks.id as id',
                'tasks.title as title',
                'tasks.status as status',
                'tasks.priority as priority',
                'tasks.due_date as due_date',
                'projects.id as project_id',
                'projects.name as project_name',
            ]);

        return array_map(
            fn (object $row): array => $this->openTask((array) $row),
            $rows->all(),
        );
    }

    /**
     * The open tasks whose due date has already passed, a task due today still
     * counting as on time.
     */
    private function overdueCount(User $user): int
    {
        return $this->forUser($user)
            ->whereNotIn('status', self::closedStatuses())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int, title: string, status: string, priority: string, due_date: string|null, overdue: bool, project: array{id: int, name: string}}
     */
    private function openTask(array $row): array
    {
        $due = $row['due_date'] === null ? null : Carbon::parse((string) $row['due_date']);

        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'status' => (string) $row['status'],
            'priority' => (string) $row['priority'],
            'due_date' => $due?->toDateString(),
            'overdue' => $due !== null && $due->isBefore(today()),
            'project' => [
                'id' => (int) $row['project_id'],
                'name' => (string) $row['project_name'],
            ],
        ];
    }

    /**
     * @param  list<array{value: string, label: string, count: int}>  $breakdown
     * @return array{open: int, done: int, total: int}
     */
    private function totals(array $breakdown): array
    {
        $total = array_sum(array_column($breakdown, 'count'));
        $done = 0;
        $cancelled = 0;

        foreach ($breakdown as $row) {
            $done += $row['value'] === TaskStatus::Done->value ? $row['count'] : 0;
            $cancelled += $row['value'] === TaskStatus::Cancelled->value ? $row['count'] : 0;
        }

        return [
            'open' => $total - $done - $cancelled,
            'done' => $done,
            'total' => $total,
        ];
    }

    /**
     * The tasks of every project the user owns or belongs to.
     *
     * @return Builder<Task>
     */
    private function forUser(User $user): Builder
    {
        return Task::query()->whereIn('project_id', $this->projectIds($user));
    }

    /**
     * The projects a user owns or has been added to, as a sub-query so the
     * scoping rule stays in the model scope instead of being written twice.
     *
     * @return QueryBuilder
     */
    private function projectIds(User $user): QueryBuilder
    {
        return Project::forUser($user)->select('id')->toBase();
    }

    /**
     * @return list<string>
     */
    private static function closedStatuses(): array
    {
        return [TaskStatus::Done->value, TaskStatus::Cancelled->value];
    }
}

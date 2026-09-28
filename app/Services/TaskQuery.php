<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * An immutable task query with chainable filters. Every filter skips empty
 * values, so a listing only states the filters it actually needs.
 */
final readonly class TaskQuery
{
    public function __construct(private Builder $query)
    {
    }

    public static function make(): self
    {
        return new self(Task::query()->with(['project:id,name', 'assignee:id,name']));
    }

    public function forProject(Project|int $project): self
    {
        return $this->with(fn (Builder $query): Builder => $query->forProject($project));
    }

    public function forProjectsOf(User $user): self
    {
        return $this->with(
            fn (Builder $query): Builder => $query->whereIn(
                'project_id',
                Project::forUser($user)->select('id'),
            ),
        );
    }

    public function assignedTo(User|int|null $assignee): self
    {
        if ($assignee === null) {
            return $this;
        }

        return $this->with(fn (Builder $query): Builder => $query->assignedTo($assignee));
    }

    public function status(?string $status): self
    {
        $status = $this->normalize($status);

        return $status === null
            ? $this
            : $this->with(fn (Builder $query): Builder => $query->withStatus($status));
    }

    public function priority(?string $priority): self
    {
        $priority = $this->normalize($priority);

        return $priority === null
            ? $this
            : $this->with(fn (Builder $query): Builder => $query->withPriority($priority));
    }

    public function due(?string $from, ?string $to): self
    {
        $from = $this->normalize($from);
        $to = $this->normalize($to);

        return $from === null && $to === null
            ? $this
            : $this->with(fn (Builder $query): Builder => $query->dueBetween($from, $to));
    }

    public function search(?string $term): self
    {
        $term = $this->normalize($term);

        return $term === null
            ? $this
            : $this->with(fn (Builder $query): Builder => $query->search($term));
    }

    /**
     * Apply a normalised filter array at once.
     *
     * @param  array<string, mixed>  $filters
     */
    public function apply(array $filters): self
    {
        return $this
            ->status($filters['status'] ?? null)
            ->priority($filters['priority'] ?? null)
            ->assignedTo($filters['assignee_id'] ?? null)
            ->due($filters['due_from'] ?? null, $filters['due_to'] ?? null)
            ->search($filters['search'] ?? null);
    }

    public function orderByPosition(): self
    {
        return $this->with(
            fn (Builder $query): Builder => $query->orderBy('position')->orderBy('id'),
        );
    }

    public function orderByDueDate(): self
    {
        return $this->with(
            fn (Builder $query): Builder => $query
                ->orderByRaw('due_date is null, due_date asc')
                ->orderBy('id'),
        );
    }

    /**
     * @return Collection<int, Task>
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * Paginate while keeping the active filters in the links, so every page of
     * a filtered listing stays a shareable URL.
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->query->paginate($perPage)->withQueryString();
    }

    private function with(callable $filter): self
    {
        return new self($filter($this->query));
    }

    private function normalize(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

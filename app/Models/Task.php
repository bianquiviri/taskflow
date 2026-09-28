<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'assignee_id',
    'title',
    'description',
    'status',
    'priority',
    'due_date',
    'position',
])]
class Task extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    /**
     * @return HasMany<TaskFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class)->oldest();
    }

    public function scopeForProject(Builder $query, Project|int $project): Builder
    {
        return $query->where('project_id', $project instanceof Project ? $project->getKey() : $project);
    }

    public function scopeAssignedTo(Builder $query, User|int $assignee): Builder
    {
        return $query->where('assignee_id', $assignee instanceof User ? $assignee->getKey() : $assignee);
    }

    public function scopeWithStatus(Builder $query, TaskStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof TaskStatus ? $status->value : $status);
    }

    public function scopeWithPriority(Builder $query, TaskPriority|string $priority): Builder
    {
        return $query->where('priority', $priority instanceof TaskPriority ? $priority->value : $priority);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.trim($term).'%';

        return $query->where(function (Builder $query) use ($needle): Builder {
            return $query->where('title', 'like', $needle)
                ->orWhere('description', 'like', $needle);
        });
    }

    public function scopeDueBetween(
        Builder $query,
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null,
    ): Builder {
        if ($from !== null) {
            $query->whereDate('due_date', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('due_date', '<=', $to);
        }

        return $query;
    }
}

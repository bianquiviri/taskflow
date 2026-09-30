<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The read model of the project history: the audit trail of a project and of
 * its tasks turned into sentences a person can read. Every entry leaves this
 * service already named, pointed at whatever it happened to and stamped with
 * the day it belongs to, so the timeline only has to lay them out.
 */
final readonly class ActivityFeedService
{
    public const int PER_PAGE = 10;

    public const string PAGE = 'activity_page';

    /**
     * The history of a project, newest first, ten entries to a page. The feed
     * pages on its own parameter so that paging the history never moves the
     * task listing of the same page.
     *
     * @return LengthAwarePaginator<int, array{id: int, event: string, actor: array{id: int|null, name: string}, label: string, target: array{type: string, id: int|null, title: string, url: string|null}, icon: string, at: string, day: string}>
     */
    public function forProject(Project $project, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->entries($project)
            ->with(['actor:id,name', 'subject'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], self::PAGE)
            ->withQueryString()
            ->through(fn (ActivityLog $entry): array => $this->describe($entry, $project));
    }

    /**
     * The entries of the project itself together with the entries of the tasks
     * that belong to it, since moving a task is part of the project history.
     *
     * @return Builder<ActivityLog>
     */
    private function entries(Project $project): Builder
    {
        $projectClass = (new Project())->getMorphClass();
        $taskClass = (new Task())->getMorphClass();

        return ActivityLog::query()
            ->where(function (Builder $query) use ($project, $projectClass, $taskClass): void {
                $query->where(
                    fn (Builder $query): Builder => $query
                        ->where('subject_type', $projectClass)
                        ->where('subject_id', $project->getKey()),
                )->orWhere(
                    fn (Builder $query): Builder => $query
                        ->where('subject_type', $taskClass)
                        ->whereIn('subject_id', Task::forProject($project)->select('id')),
                );
            });
    }

    /**
     * One entry as the timeline reads it: who did it, what they did, what they
     * did it to and when. An entry outlives the person behind it, so a missing
     * actor falls back to a name rather than leaving a hole in the sentence.
     *
     * @return array{id: int, event: string, actor: array{id: int|null, name: string}, label: string, target: array{type: string, id: int|null, title: string, url: string|null}, icon: string, at: string, day: string}
     */
    private function describe(ActivityLog $entry, Project $project): array
    {
        $event = ActivityEvent::from((string) $entry->getRawOriginal('event'));

        /** @var User|null $actor */
        $actor = $entry->actor;

        [$label, $icon] = $this->describeEvent($event, $this->statusChange($event, $entry));

        return [
            'id' => $entry->getKey(),
            'event' => $event->value,
            'actor' => [
                'id' => $actor?->getKey(),
                'name' => $actor instanceof User ? $actor->name : 'Former member',
            ],
            'label' => $label,
            'target' => $this->describeSubject($entry, $project),
            'icon' => $icon,
            'at' => $entry->created_at->toIso8601String(),
            'day' => $entry->created_at->toDateString(),
        ];
    }

    /**
     * Where a status change landed, read from the metadata of the entry. Only
     * a status change carries one, and an entry written before the status was
     * known leaves the phrase without a destination.
     */
    private function statusChange(ActivityEvent $event, ActivityLog $entry): ?TaskStatus
    {
        return $event === ActivityEvent::TaskStatusChanged
            ? TaskStatus::tryFrom((string) data_get($entry->meta, 'to'))
            : null;
    }

    /**
     * The phrase and the icon of an event.
     *
     * @return array{string, string}
     */
    private function describeEvent(ActivityEvent $event, ?TaskStatus $landed): array
    {
        return match ($event) {
            ActivityEvent::ProjectCreated => ['created the project', 'plus'],
            ActivityEvent::ProjectUpdated => ['updated the project', 'settings'],
            ActivityEvent::ProjectArchived => ['archived the project', 'warning'],
            ActivityEvent::TaskCreated => ['added the task', 'tasks'],
            ActivityEvent::TaskUpdated => ['updated the task', 'settings'],
            ActivityEvent::TaskStatusChanged => $landed === null
                ? ['changed the status of the task', 'check']
                : ["moved the task to {$landed->label()}", 'check'],
            default => ['performed an action', 'info'],
        };
    }

    /**
     * What the entry happened to, linked while it still exists. A task that is
     * gone takes its entries with it, since the feed reads the history of the
     * tasks the project has now; anything else is the project itself.
     *
     * @return array{type: string, id: int|null, title: string, url: string|null}
     */
    private function describeSubject(ActivityLog $entry, Project $project): array
    {
        $subject = $entry->subject;

        if ($subject instanceof Task) {
            return [
                'type' => $subject->getMorphClass(),
                'id' => $subject->getKey(),
                'title' => $subject->title,
                'url' => route('tasks.show', $subject),
            ];
        }

        return [
            'type' => $project->getMorphClass(),
            'id' => $project->getKey(),
            'title' => $project->name,
            'url' => route('projects.show', $project),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\LogActivityAction;
use App\Enums\ActivityEvent;
use App\Models\Project;
use Illuminate\Support\Arr;

final readonly class ProjectObserver
{
    public function __construct(private LogActivityAction $logActivity)
    {
    }

    public function created(Project $project): void
    {
        ($this->logActivity)(ActivityEvent::ProjectCreated, $project, ['name' => $project->name]);
    }

    public function updated(Project $project): void
    {
        if ($project->wasChanged('archived_at')) {
            ($this->logActivity)(ActivityEvent::ProjectArchived, $project);

            return;
        }

        ($this->logActivity)(ActivityEvent::ProjectUpdated, $project, [
            'changes' => Arr::except($project->getChanges(), ['updated_at']),
        ]);
    }
}

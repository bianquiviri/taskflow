<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;

final readonly class ArchiveProjectAction
{
    public function __invoke(Project $project): Project
    {
        if ($project->archived_at === null) {
            $project->update(['archived_at' => now()]);
        }

        return $project;
    }
}

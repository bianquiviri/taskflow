<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;

final readonly class UpdateProjectAction
{
    /**
     * @param  array{name?: string, description?: string|null}  $data
     */
    public function __invoke(Project $project, array $data): Project
    {
        $project->update($data);

        return $project;
    }
}

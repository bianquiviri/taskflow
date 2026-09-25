<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateProjectAction
{
    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function __invoke(User $owner, array $data): Project
    {
        return DB::transaction(function () use ($owner, $data): Project {
            $project = Project::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => $owner->id,
            ]);

            $project->members()->create([
                'user_id' => $owner->id,
                'role' => ProjectRole::Owner,
            ]);

            return $project;
        });
    }
}

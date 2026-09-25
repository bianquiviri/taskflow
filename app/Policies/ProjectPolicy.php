<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->isMember($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->isManager($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->isManager($user, $project);
    }

    private function isMember(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
    }

    private function isManager(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)
                ->whereIn('role', [ProjectRole::Owner, ProjectRole::Admin])
                ->exists();
    }
}

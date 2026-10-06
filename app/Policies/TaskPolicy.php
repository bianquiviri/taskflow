<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class TaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->isProjectMember($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->isProjectManager($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->isProjectMember($user, $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isAssignedMember($user, $task) || $this->isTaskManager($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->isTaskManager($user, $task);
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $this->isAssignedMember($user, $task) || $this->isTaskManager($user, $task);
    }

    private function isAssignedMember(User $user, Task $task): bool
    {
        return $task->assignee_id === $user->getKey()
            && $this->isProjectMember($user, $task->project);
    }

    private function isTaskManager(User $user, Task $task): bool
    {
        return $this->isProjectManager($user, $task->project);
    }

    private function isProjectMember(User $user, Project $project): bool
    {
        return $project->owner_id === $user->getKey()
            || $project->members()->where('user_id', $user->getKey())->exists();
    }

    private function isProjectManager(User $user, Project $project): bool
    {
        return $project->owner_id === $user->getKey()
            || $project->members()->where('user_id', $user->getKey())
                ->whereIn('role', [ProjectRole::Owner, ProjectRole::Admin])
                ->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class TaskFilePolicy
{
    use HandlesAuthorization;

    /**
     * Seeing what is attached to a task.
     */
    public function view(User $user, TaskFile $taskFile): bool
    {
        return $this->isProjectMember($user, $this->project($taskFile));
    }

    /**
     * Reading the bytes of an attachment: every member of the project may
     * download what the team put on the task.
     */
    public function download(User $user, TaskFile $taskFile): bool
    {
        return $this->isProjectMember($user, $this->project($taskFile));
    }

    public function create(User $user, Task $task): bool
    {
        return $this->isProjectMember($user, $this->projectOf($task));
    }

    /**
     * Removing an attachment: the member who put it there, or a manager of the
     * project, who may also clean up what others uploaded. A member who left
     * the project keeps neither the right to see nor to remove the file.
     */
    public function delete(User $user, TaskFile $taskFile): bool
    {
        $project = $this->project($taskFile);

        return $taskFile->user_id === $user->getKey() && $this->isProjectMember($user, $project)
            || $this->isProjectManager($user, $project);
    }

    private function project(TaskFile $taskFile): Project
    {
        return $this->projectOf($taskFile->task);
    }

    /**
     * The project a task belongs to. A task always has one, so the relation is
     * read as a project instead of as an optional model.
     */
    private function projectOf(Task $task): Project
    {
        /** @var Project $project */
        $project = $task->project;

        return $project;
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

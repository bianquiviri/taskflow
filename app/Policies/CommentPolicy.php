<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class CommentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->isProjectMember($user, $project);
    }

    public function view(User $user, Comment $comment): bool
    {
        return $this->isProjectMember($user, $comment->task->project);
    }

    public function create(User $user, Task $task): bool
    {
        return $this->isProjectMember($user, $task->project);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey();
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey();
    }

    private function isProjectMember(User $user, Project $project): bool
    {
        return $project->owner_id === $user->getKey()
            || $project->members()->where('user_id', $user->getKey())->exists();
    }
}

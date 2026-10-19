<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AddCommentAction;
use App\Actions\ChangeTaskStatusAction;
use App\Actions\CreateProjectAction;
use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * A realistic, self-contained demo dataset for local testing and demos.
 *
 * It fills every domain table: users, teams and their memberships and
 * invitations, projects with members, tasks in every status (each moving
 * through its real transitions so the audit trail is populated), comments with
 * mentions, task attachments and the notified users that follow. The last user
 * is deliberately left out of every team and project so the "forbidden" paths
 * can be exercised by logging in as them.
 *
 * Every user shares the password "password".
 */
class DemoSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    /**
     * @var array<string, User>
     */
    private array $users = [];

    private Team $team;

    public function run(): void
    {
        $this->users();
        $this->team = $this->team();
        $this->teamMembers();
        $this->invitations();

        $website = $this->project('owner', 'Website redesign', 'Rebuild the marketing site with the new design system.');
        $mobile = $this->project('owner', 'Mobile app', 'Ship the first version of the iOS and Android apps.');
        $tooling = $this->project('admin', 'Internal tooling', 'Automation for the support team.');

        $this->projectMembers($website, ['owner', 'admin', 'member'], lead: 'member');
        $this->projectMembers($mobile, ['owner', 'admin', 'member']);
        $this->projectMembers($tooling, ['admin', 'owner']);

        $this->tasks($website, ['owner', 'admin', 'member']);
        $this->tasks($mobile, ['admin', 'member']);
        $this->tasks($tooling, ['owner', 'admin']);

        $this->archivedProject();

        Auth::logout();
    }

    /**
     * Seed one user per role, keyed by the handle used across the dataset.
     */
    private function users(): void
    {
        $names = [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'member' => 'Member',
            'second' => 'Second',
            'outsider' => 'Outsider',
        ];

        foreach ($names as $handle => $name) {
            $this->users[$handle] = User::factory()->create([
                'name' => $name,
                'email' => "{$handle}@taskflow.test",
                'password' => self::PASSWORD,
            ]);
        }
    }

    private function team(): Team
    {
        // The TeamObserver adds the owner as an Owner member on create.
        return Team::factory()->create([
            'name' => 'TaskFlow HQ',
            'owner_id' => $this->users['owner']->id,
        ]);
    }

    private function teamMembers(): void
    {
        $memberships = [
            ['admin', TeamRole::Admin],
            ['member', TeamRole::Member],
            ['second', TeamRole::Member],
        ];

        foreach ($memberships as [$handle, $role]) {
            TeamMember::factory()->create([
                'team_id' => $this->team->id,
                'user_id' => $this->users[$handle]->id,
                'role' => $role,
            ]);
        }
    }

    /**
     * One pending invitation, one that already expired and one that was
     * revoked, so every state the settings screen paints has data behind it.
     */
    private function invitations(): void
    {
        TeamInvitation::factory()->create([
            'team_id' => $this->team->id,
            'email' => 'pending@taskflow.test',
            'role' => TeamRole::Member,
            'expires_at' => now()->addDays(7),
        ]);

        TeamInvitation::factory()->create([
            'team_id' => $this->team->id,
            'email' => 'expired@taskflow.test',
            'role' => TeamRole::Admin,
            'expires_at' => now()->subDays(2),
        ]);

        TeamInvitation::factory()->create([
            'team_id' => $this->team->id,
            'email' => 'revoked@taskflow.test',
            'role' => TeamRole::Member,
            'expires_at' => now()->addDays(7),
            'revoked_at' => now()->subDay(),
        ]);
    }

    private function project(string $ownerHandle, string $name, string $description): Project
    {
        // Acting as the owner keeps the audit trail attributed to a real user.
        Auth::login($this->users[$ownerHandle]);

        return app(CreateProjectAction::class)($this->users[$ownerHandle], [
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * @param  list<string>  $handles
     */
    private function projectMembers(Project $project, array $handles, ?string $lead = null): void
    {
        foreach ($handles as $handle) {
            $user = $this->users[$handle];

            $project->members()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $user->id === $project->owner_id ? ProjectRole::Owner : ProjectRole::Member],
            );
        }

        if ($lead !== null) {
            $project->members()->updateOrCreate(
                ['user_id' => $this->users[$lead]->id],
                ['role' => ProjectRole::Admin],
            );
        }
    }

    /**
     * Seven tasks per project: one per status, moved through their real
     * transitions so the board, the filters and the timeline all have data.
     *
     * @param  list<string>  $assignees
     */
    private function tasks(Project $project, array $assignees): void
    {
        $plan = [
            ['Draft the sitemap', TaskStatus::Done, TaskPriority::Medium, -7],
            ['Design the landing page', TaskStatus::InReview, TaskPriority::High, -1],
            ['Build the pricing section', TaskStatus::InProgress, TaskPriority::Urgent, 0],
            ['Write the copy', TaskStatus::Todo, TaskPriority::Medium, 3],
            ['Fix the mobile nav', TaskStatus::Todo, TaskPriority::Urgent, -2],
            ['Set up analytics', TaskStatus::Todo, TaskPriority::Low, 10],
            ['Old spike', TaskStatus::Cancelled, TaskPriority::Low, -20],
        ];

        foreach ($plan as $position => [$title, $status, $priority, $dueOffset]) {
            $assignee = $this->users[$assignees[$position % count($assignees)]];

            $task = Task::factory()->create([
                'project_id' => $project->id,
                'assignee_id' => $assignee->id,
                'title' => $title,
                'status' => TaskStatus::Todo,
                'priority' => $priority,
                'due_date' => now()->addDays($dueOffset)->toDateString(),
                'position' => $position,
            ]);

            $this->advanceTo($task, $status);
            $this->conversation($task, $assignee);
            $this->attachment($task, $assignee);
        }
    }

    /**
     * Move a task from To Do to its target status through the transitions the
     * domain allows, so every step lands in the audit trail.
     */
    private function advanceTo(Task $task, TaskStatus $target): void
    {
        $path = match ($target) {
            TaskStatus::Todo => [],
            TaskStatus::InProgress => [TaskStatus::InProgress],
            TaskStatus::InReview => [TaskStatus::InProgress, TaskStatus::InReview],
            TaskStatus::Done => [TaskStatus::InProgress, TaskStatus::InReview, TaskStatus::Done],
            TaskStatus::Cancelled => [TaskStatus::Cancelled],
        };

        $change = app(ChangeTaskStatusAction::class);

        foreach ($path as $status) {
            $change($task, $status);
        }
    }

    /**
     * A short thread on the task. The last comment mentions a teammate, which
     * writes a notification for the person named.
     */
    private function conversation(Task $task, User $assignee): void
    {
        $author = $task->project->owner;
        $addComment = app(AddCommentAction::class);

        Auth::login($author);

        $addComment($task, $author, 'Kicking this off — see the brief in the attachment.');
        $addComment($task, $assignee, 'On it.');
        $addComment($task, $author, 'Thanks @Member, ping me when the copy is ready.');
    }

    /**
     * One real, downloadable attachment per task, written to the private disk
     * so the download route resolves instead of 404ing in the demo.
     */
    private function attachment(Task $task, User $uploader): void
    {
        $file = TaskFile::factory()->create([
            'task_id' => $task->id,
            'user_id' => $uploader->id,
            'original_name' => 'brief.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $contents = "TaskFlow demo attachment for \"{$task->title}\".";

        Storage::disk($file->disk)->put($file->path, $contents);

        $file->forceFill(['size' => strlen($contents)])->save();
    }

    private function archivedProject(): void
    {
        $project = $this->project('owner', 'Legacy migration', 'Finished and archived.');

        Auth::login($this->users['owner']);
        $project->forceFill(['archived_at' => now()])->save();
    }
}

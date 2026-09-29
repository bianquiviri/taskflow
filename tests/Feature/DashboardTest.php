<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('shows an empty dashboard to a brand new user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Dashboard/Index')
            ->where('kpis.projects', 0)
            ->where('kpis.open', 0)
            ->where('kpis.overdue', 0)
            ->where('kpis.done', 0)
            ->where('kpis.total', 0)
            ->has('recentProjects', 0)
            ->has('openTasks', 0)
            ->has('statusBreakdown', 5)
            ->where('statusBreakdown.0.value', 'todo')
            ->where('statusBreakdown.0.count', 0),
        );
});

it('counts the projects and tasks of the signed in user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    Project::factory()->archived()->for($user, 'owner')->create();

    Task::factory()->count(3)->for($project, 'project')->create();
    Task::factory()->for($project, 'project')->create(['status' => TaskStatus::InProgress]);
    Task::factory()->for($project, 'project')->create(['status' => TaskStatus::Done]);
    Task::factory()->for($project, 'project')->create(['status' => TaskStatus::Cancelled]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('kpis.projects', 1)
            ->where('kpis.open', 4)
            ->where('kpis.done', 1)
            ->where('kpis.total', 6)
            ->where('statusBreakdown.0.count', 3)
            ->where('statusBreakdown.1.count', 1)
            ->where('statusBreakdown.3.count', 1)
            ->where('statusBreakdown.4.count', 1),
        );
});

it('ignores the projects of other users', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $shared = Project::factory()->for($stranger, 'owner')->create();
    $joined = Project::factory()->for($stranger, 'owner')->create();
    $joined->members()->create(['user_id' => $user->id, 'role' => 'member']);

    Task::factory()->for($shared, 'project')->create();
    Task::factory()->for($joined, 'project')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('kpis.projects', 1)
            ->where('kpis.open', 1)
            ->has('recentProjects', 1)
            ->where('recentProjects.0.id', $joined->id),
        );
});

it('counts only the open tasks as overdue', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    Task::factory()->for($project, 'project')->create(['due_date' => now()->subDay()]);
    Task::factory()->for($project, 'project')->create(['due_date' => now()->subWeek()]);
    Task::factory()->for($project, 'project')->create(['due_date' => now()->addDay()]);
    Task::factory()->for($project, 'project')->create(['due_date' => now()->subDay(), 'status' => TaskStatus::Done]);
    Task::factory()->for($project, 'project')->create(['due_date' => now()->subDay(), 'status' => TaskStatus::Cancelled]);
    Task::factory()->for($project, 'project')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kpis.overdue', 2));
});

it('leaves a task due today out of the overdue count', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    Task::factory()->for($project, 'project')->create(['due_date' => today()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->where('kpis.overdue', 0)
            ->where('openTasks.0.overdue', false),
        );
});

it('lists the five most recently created active projects with their task counts', function () {
    $user = User::factory()->create();

    $oldest = Project::factory()->for($user, 'owner')->create(['created_at' => now()->subDays(6)]);
    $newest = Project::factory()->for($user, 'owner')->create(['created_at' => now()->subDay()]);
    Project::factory()->archived()->for($user, 'owner')->create(['created_at' => now()]);

    foreach (range(1, 4) as $index) {
        Project::factory()->for($user, 'owner')->create(['created_at' => now()->subDays(10 - $index)]);
    }

    Task::factory()->count(3)->for($newest, 'project')->create();
    Task::factory()->for($newest, 'project')->create(['status' => TaskStatus::Done]);
    Task::factory()->for($oldest, 'project')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('recentProjects', 5)
            ->where('recentProjects.0.id', $newest->id)
            ->where('recentProjects.0.tasks', 4)
            ->where('recentProjects.0.open', 3),
        );
});

it('lists the open tasks soonest due date first and flags the overdue ones', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create(['name' => 'Website']);

    $overdue = Task::factory()->for($project, 'project')->create([
        'title' => 'Fix the checkout',
        'due_date' => now()->subDays(2),
    ]);
    $later = Task::factory()->for($project, 'project')->create([
        'title' => 'Ship the landing page',
        'due_date' => now()->addDays(5),
    ]);
    $undated = Task::factory()->for($project, 'project')->create();
    Task::factory()->for($project, 'project')->create(['status' => TaskStatus::Done]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->has('openTasks', 3)
            ->where('openTasks.0.id', $overdue->id)
            ->where('openTasks.0.overdue', true)
            ->where('openTasks.0.project.name', 'Website')
            ->where('openTasks.1.id', $later->id)
            ->where('openTasks.1.overdue', false)
            ->where('openTasks.2.id', $undated->id)
            ->where('openTasks.2.due_date', null),
        );
});

it('caps the open task list so the dashboard stays a summary', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    Task::factory()->count(12)->for($project, 'project')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('openTasks', 8)->where('kpis.open', 12));
});

it('keeps the dashboard behind authentication and a verified address', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

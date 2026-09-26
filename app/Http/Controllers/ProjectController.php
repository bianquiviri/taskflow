<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ArchiveProjectAction;
use App\Actions\CreateProjectAction;
use App\Actions\UpdateProjectAction;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly CreateProjectAction $createProject,
        private readonly UpdateProjectAction $updateProject,
        private readonly ArchiveProjectAction $archiveProject,
        private readonly ActivityLogService $activityLog,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->forUser($request->user())
            ->active()
            ->latest()
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'activity' => $this->activityLog->forSubject($project),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = ($this->createProject)($request->user(), $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        ($this->updateProject)($project, $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        ($this->archiveProject)($project);

        return redirect()->route('projects.index')->with('success', 'Project archived.');
    }
}

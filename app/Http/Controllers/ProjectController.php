<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ArchiveProjectAction;
use App\Actions\CreateProjectAction;
use App\Actions\UpdateProjectAction;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\TaskFilterRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ActivityLogService;
use App\Services\ProjectOverviewService;
use App\Services\TaskQueryService;
use App\Support\TaskStatuses;
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
        private readonly ProjectOverviewService $overview,
        private readonly TaskQueryService $taskQuery,
        private readonly TaskStatuses $taskStatuses,
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

    /**
     * The project page: its board, the people on it, the audit trail and the
     * progress of its tasks. The board and the list share one set of filters so
     * both views stay on the same shareable URL.
     */
    public function show(TaskFilterRequest $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $filters = $request->filters();
        $tasks = $this->taskQuery->forProject($project, $filters);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'view' => $this->taskView($request),
            'tasks' => $tasks,
            'filters' => $filters,
            'filterOptions' => $this->taskQuery->filterOptions($project),
            'statuses' => $this->taskStatuses->columns(),
            'members' => $this->overview->people($project),
            'progress' => $this->overview->progress($project),
            'permissions' => $this->overview->permissions($project, $request->user(), $tasks->items()),
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

    /**
     * How the project page lists its tasks: the board by default, the filtered
     * list on request.
     */
    private function taskView(Request $request): string
    {
        return $request->query('view') === 'list' ? 'list' : 'board';
    }
}

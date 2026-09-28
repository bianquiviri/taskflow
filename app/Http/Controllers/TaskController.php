<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssignTaskAction;
use App\Actions\ChangeTaskStatusAction;
use App\Actions\CreateTaskAction;
use App\Actions\UpdateTaskAction;
use App\Enums\TaskStatus;
use App\Http\Requests\AssignTaskRequest;
use App\Http\Requests\ChangeTaskStatusRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TaskFilterRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskQueryService;
use App\Support\TaskStatuses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
        private readonly UpdateTaskAction $updateTask,
        private readonly AssignTaskAction $assignTask,
        private readonly ChangeTaskStatusAction $changeTaskStatus,
        private readonly TaskQueryService $taskQuery,
        private readonly TaskStatuses $taskStatuses,
    ) {
    }

    public function index(TaskFilterRequest $request, Project $project): Response
    {
        $this->authorize('viewAny', [Task::class, $project]);

        $filters = $request->filters();

        return Inertia::render('Tasks/Index', [
            'project' => $project,
            'tasks' => $this->taskQuery->forProject($project, $filters),
            'filters' => $filters,
            'filterOptions' => $this->taskQuery->filterOptions($project),
        ]);
    }

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        return Inertia::render('Tasks/Show', [
            'task' => $task->load(['project', 'assignee']),
            'comments' => $task->comments()->with('user:id,name')->get(),
            'priorities' => $this->taskQuery->filterOptions()['priorities'],
            'statuses' => $this->taskStatuses->columns(),
            'permissions' => array_keys(array_filter([
                'tasks.update' => Gate::allows('update', $task),
                'tasks.changeStatus' => Gate::allows('changeStatus', $task),
            ])),
        ]);
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Task::class, $project]);

        $task = ($this->createTask)($project, $request->validated());

        return $this->respond($request, $task, 'Task created.');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        ($this->updateTask)($task, $request->validated());

        return $this->respond($request, $task, 'Task updated.');
    }

    public function assign(AssignTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('assign', $task);

        $assigneeId = $request->validated('assignee_id');
        $assignee = $assigneeId === null ? null : User::findOrFail($assigneeId);
        ($this->assignTask)($task, $assignee);

        return $this->respond($request, $task, 'Task assignment updated.');
    }

    public function status(ChangeTaskStatusRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('changeStatus', $task);

        ($this->changeTaskStatus)($task, TaskStatus::from($request->validated('status')));

        return $this->respond($request, $task, 'Task status updated.');
    }

    /**
     * A mutation started from a modal or a board drop must keep the user where
     * they are, so it sends the Inertia client back to the page it came from,
     * which it follows in place. A plain form post still lands on the task
     * page, where the flash confirms the change.
     */
    private function respond(Request $request, Task $task, string $message): RedirectResponse
    {
        if ($request->header('X-Inertia') !== null) {
            return back();
        }

        return redirect()->route('tasks.show', $task)->with('success', $message);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MyTaskIndexRequest;
use App\Services\TaskQueryService;
use Inertia\Inertia;
use Inertia\Response;

class MyTaskController extends Controller
{
    public function __construct(
        private readonly TaskQueryService $taskQuery,
    ) {
    }

    /**
     * Every task assigned to the signed in user, across the projects they
     * belong to, with the shared filters and pagination.
     */
    public function index(MyTaskIndexRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('Tasks/Mine', [
            'tasks' => $this->taskQuery->forAssignee($request->user(), $filters),
            'filters' => $filters,
            'filterOptions' => $this->taskQuery->filterOptions(),
        ]);
    }
}

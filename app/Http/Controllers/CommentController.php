<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddCommentAction;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function __construct(
        private readonly AddCommentAction $addComment,
    ) {
    }

    public function index(Task $task): JsonResponse
    {
        $this->authorize('viewAny', [Comment::class, $task->project]);

        $comments = $task->comments()->with('user:id,name')->get();

        return response()->json(['data' => $comments]);
    }

    public function store(StoreCommentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('create', [Comment::class, $task]);

        ($this->addComment)($task, $request->user(), $request->validated('body'));

        return redirect()->route('tasks.show', $task)->with('success', 'Comment added.');
    }
}

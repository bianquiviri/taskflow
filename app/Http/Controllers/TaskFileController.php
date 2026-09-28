<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteTaskFileAction;
use App\Actions\StoreTaskFileAction;
use App\Http\Requests\StoreTaskFileRequest;
use App\Models\Task;
use App\Models\TaskFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskFileController extends Controller
{
    public function __construct(
        private readonly StoreTaskFileAction $storeTaskFile,
        private readonly DeleteTaskFileAction $deleteTaskFile,
    ) {
    }

    public function store(StoreTaskFileRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('create', [TaskFile::class, $task]);

        ($this->storeTaskFile)($task, $request->user(), $request->attachment());

        return redirect()->route('tasks.show', $task)->with('success', 'File attached.');
    }

    /**
     * Streams the stored bytes to a member of the project. The response never
     * carries the name the file was stored under, and it is sent as an
     * attachment so that nothing is rendered inline.
     */
    public function download(Task $task, TaskFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404);

        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
        ]);
    }

    public function destroy(Task $task, TaskFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        ($this->deleteTaskFile)($file);

        return back()->with('success', 'File removed.');
    }
}

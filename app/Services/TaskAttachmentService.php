<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Support\AttachmentRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * How the attachments of a task reach a page: what to show, where to download
 * it from, and whether the viewer may remove it. The stored name and the disk
 * stay on the server, so a page can only link to the download endpoint.
 */
final readonly class TaskAttachmentService
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     mime_type: string,
     *     size: int,
     *     human_size: string,
     *     uploaded_by: string,
     *     uploaded_at: string|null,
     *     download_url: string,
     *     delete_url: string,
     *     can_delete: bool
     * }>
     */
    public function forTask(Task $task, User $viewer): array
    {
        $files = $task->files()
            ->with('user:id,name')
            ->get();

        $gate = Gate::forUser($viewer);

        return $files
            ->map(fn (TaskFile $taskFile): array => [
                'id' => $taskFile->getKey(),
                'name' => $taskFile->original_name,
                'mime_type' => $taskFile->mime_type,
                'size' => $taskFile->size,
                'human_size' => Number::fileSize($taskFile->size),
                'uploaded_by' => $taskFile->user->name,
                'uploaded_at' => $taskFile->created_at?->toIso8601String(),
                'download_url' => route('tasks.files.download', [$task, $taskFile]),
                'delete_url' => route('tasks.files.destroy', [$task, $taskFile]),
                'can_delete' => $gate->allows('delete', $taskFile),
            ])
            ->values()
            ->all();
    }

    /**
     * What the upload form may offer, taken from the same rules the upload is
     * validated against.
     *
     * @return array{max_size: string, extensions: list<string>}
     */
    public function formOptions(): array
    {
        return [
            'max_size' => AttachmentRules::maxSizeLabel(),
            'extensions' => AttachmentRules::extensions(),
        ];
    }
}

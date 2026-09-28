<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Support\AttachmentRules;
use Illuminate\Http\UploadedFile;

/**
 * Stores an upload on the private attachment disk and records it against the
 * task. The name the file was sent with is kept for display only: the file
 * itself is written under a generated name inside the folder of its task, so a
 * crafted or colliding name can never reach the filesystem, and two uploads of
 * the same file never overwrite each other.
 */
final readonly class StoreTaskFileAction
{
    public function __invoke(Task $task, User $uploader, UploadedFile $upload): TaskFile
    {
        $path = $upload->store("tasks/{$task->getKey()}", AttachmentRules::DISK);

        return $task->files()->create([
            'user_id' => $uploader->getKey(),
            'disk' => AttachmentRules::DISK,
            'path' => $path,
            'original_name' => basename($upload->getClientOriginalName()),
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
        ]);
    }
}

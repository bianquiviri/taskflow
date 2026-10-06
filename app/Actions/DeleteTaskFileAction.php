<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\TaskFile;
use Illuminate\Support\Facades\Storage;

/**
 * Removes an attachment: the stored file goes first, so a record is never left
 * pointing at bytes that were never deleted. A file that is already gone is not
 * an error, the row still has to disappear.
 */
final readonly class DeleteTaskFileAction
{
    public function __invoke(TaskFile $taskFile): void
    {
        $disk = Storage::disk($taskFile->disk);

        if ($disk->exists($taskFile->path)) {
            $disk->delete($taskFile->path);
        }

        $taskFile->delete();
    }
}

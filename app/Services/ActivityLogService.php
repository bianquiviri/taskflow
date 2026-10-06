<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

final readonly class ActivityLogService
{
    /**
     * Fetch the paginated audit trail of a single subject, newest first.
     */
    public function forSubject(Model $subject, int $perPage = 15): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->forSubject($subject)
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

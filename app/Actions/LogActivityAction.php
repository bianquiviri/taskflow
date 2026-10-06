<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final readonly class LogActivityAction
{
    /**
     * Append an immutable audit entry for the given subject.
     *
     * @param  array<string, mixed>  $meta
     */
    public function __invoke(ActivityEvent $event, Model $subject, array $meta = []): ActivityLog
    {
        return ActivityLog::create([
            'actor_id' => Auth::user()?->getKey(),
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}

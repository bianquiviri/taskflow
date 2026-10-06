<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'event' => ActivityEvent::ProjectCreated,
            'subject_type' => (new Project())->getMorphClass(),
            'subject_id' => Project::factory(),
            'meta' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use App\Support\AttachmentRules;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\TaskFile>
 */
class TaskFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'disk' => AttachmentRules::DISK,
            'path' => 'tasks/'.fake()->uuid().'/'.Str::random(40).'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 5 * 1024 * 1024),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email' => fake()->safeEmail(),
            'token_hash' => Hash::make(Str::random(40)),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateTeamAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function __invoke(User $owner, array $data): Team
    {
        return DB::transaction(function () use ($owner, $data): Team {
            return Team::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'owner_id' => $owner->id,
            ]);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $counter = 2;

        while (Team::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}

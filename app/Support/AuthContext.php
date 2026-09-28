<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Derives the auth context shared with every Inertia page: the signed-in user,
 * the current team and the permissions that need no model instance to resolve.
 *
 * The payload is resolved at most once per user, so a single request never
 * repeats the same lookup or policy check.
 */
final class AuthContext
{
    /**
     * @var array{user: array{id: int, name: string, email: string}|null, team: array{id: int, name: string, slug: string, role: string}|null, can: list<string>}|null
     */
    private ?array $payload = null;

    /**
     * The user the cached payload belongs to.
     */
    private ?int $resolvedFor = null;

    /**
     * @return array{user: array{id: int, name: string, email: string}|null, team: array{id: int, name: string, slug: string, role: string}|null, can: list<string>}
     */
    public function resolve(?User $user): array
    {
        if ($this->payload === null || $this->resolvedFor !== $user?->getKey()) {
            $this->resolvedFor = $user?->getKey();
            $this->payload = $this->build($user);
        }

        return $this->payload;
    }

    /**
     * @return array{user: array{id: int, name: string, email: string}|null, team: array{id: int, name: string, slug: string, role: string}|null, can: list<string>}
     */
    private function build(?User $user): array
    {
        if ($user === null) {
            return ['user' => null, 'team' => null, 'can' => []];
        }

        $membership = $user->currentMembership();

        return [
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
            'team' => $this->team($membership?->team, $membership),
            'can' => $this->permissions($user, $membership?->team),
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, role: string}|null
     */
    private function team(?Team $team, ?TeamMember $membership): ?array
    {
        if ($team === null || $membership === null) {
            return null;
        }

        return [
            'id' => $team->getKey(),
            'name' => $team->name,
            'slug' => $team->slug,
            'role' => $membership->role->value,
        ];
    }

    /**
     * Only abilities answerable without a model instance are shared: anything
     * bound to a project or a task stays on the page owning that entity, which
     * merges it through `useCan`.
     *
     * @return list<string>
     */
    private function permissions(User $user, ?Team $team): array
    {
        $gate = Gate::forUser($user);

        $abilities = [
            'projects.viewAny' => $gate->allows('viewAny', Project::class),
            'projects.create' => $gate->allows('create', Project::class),
            'teams.view' => $team !== null,
            'teams.manageMembers' => $team !== null && $gate->allows('manageMembers', $team),
        ];

        return array_keys(array_filter($abilities));
    }
}

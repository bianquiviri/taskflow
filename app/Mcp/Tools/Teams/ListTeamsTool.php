<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Mcp\Support\McpResponse;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Auth;
use PhpMcp\Server\Attributes\McpTool;

final class ListTeamsTool
{
    #[McpTool(name: 'taskflow.teams.list', description: 'List teams the authenticated user belongs to.')]
    public function handle(): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Not authenticated in MCP context.');
        }

        $teams = Team::query()
            ->whereHas('members', function ($q) use ($user): void {
                $q->where('user_id', $user->getKey());
            })
            ->with(['members' => function ($q) use ($user): void {
                $q->where('user_id', $user->getKey());
            }])
            ->orderBy('name')
            ->get();

        $items = [];
        foreach ($teams as $team) {
            /** @var TeamMember|null $membership */
            $membership = $team->members->first();
            $role = null;
            if ($membership !== null) {
                $rawRole = $membership->getAttribute('role');
                if (is_object($rawRole)) {
                    if (method_exists($rawRole, 'value')) {
                        $val = $rawRole->value;
                        $role = is_string($val) || is_int($val) ? $val : (string) $val;
                    } else {
                        $role = (string) $rawRole;
                    }
                } elseif (is_string($rawRole) || is_int($rawRole)) {
                    $role = $rawRole;
                } elseif ($rawRole !== null) {
                    $role = (string) $rawRole;
                }
            }

            $items[] = [
                'key' => $team->getRouteKey(),
                'id' => $team->getKey(),
                'name' => is_string($team->getAttribute('name')) ? $team->getAttribute('name') : (string) $team->getAttribute('name'),
                'role' => $role,
            ];
        }

        return McpResponse::json([
            'teams' => $items,
            'count' => count($items),
        ]);
    }
}

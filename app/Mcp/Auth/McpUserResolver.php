<?php

declare(strict_types=1);

namespace App\Mcp\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class McpUserResolver
{
    public function resolve(): User
    {
        $userId = config('mcp.user.id');
        $userEmail = config('mcp.user.email');

        if (is_string($userId) && $userId !== '') {
            $user = User::find((int) $userId);

            if (! $user instanceof User) {
                throw new \RuntimeException("MCP_USER_ID [{$userId}] does not match any user.");
            }

            Auth::login($user);

            return $user;
        }

        if (is_string($userEmail) && $userEmail !== '') {
            $user = User::where('email', $userEmail)->first();

            if (! $user instanceof User) {
                throw new \RuntimeException("MCP_USER_EMAIL [{$userEmail}] does not match any user.");
            }

            Auth::login($user);

            return $user;
        }

        throw new \RuntimeException('MCP server requires MCP_USER_ID or MCP_USER_EMAIL to be set in the environment.');
    }
}

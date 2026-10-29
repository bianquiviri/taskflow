<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mcp\Auth\McpUserResolver;
use Illuminate\Support\ServiceProvider;

final class McpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->runningInConsole() && $this->isMcpServe()) {
            app(McpUserResolver::class)->resolve();
        }
    }

    private function isMcpServe(): bool
    {
        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $arg) {
            if (is_string($arg) && $arg === 'mcp:serve') {
                return true;
            }
        }

        return false;
    }
}

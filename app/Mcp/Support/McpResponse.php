<?php

declare(strict_types=1);

namespace App\Mcp\Support;

final class McpResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Parses `@handle` mentions out of plain text and derives the handle of a user
 * from their display name.
 */
final class MentionParser
{
    /**
     * A mention is an `@` preceded by the start of the text, a space or a
     * punctuation mark, so e-mail addresses are never read as mentions.
     */
    private const PATTERN = '/(?<![\w.\-])@([a-z0-9]+(?:-[a-z0-9]+)*)/i';

    /**
     * @return list<string>
     */
    public static function handles(string $body): array
    {
        preg_match_all(self::PATTERN, $body, $matches);

        $handles = array_map(strtolower(...), $matches[1] ?? []);

        return array_values(array_unique($handles));
    }

    public static function handleFor(string $name): string
    {
        return Str::slug($name);
    }
}

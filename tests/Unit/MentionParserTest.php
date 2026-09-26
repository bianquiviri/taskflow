<?php

declare(strict_types=1);

use App\Support\MentionParser;

it('extracts unique mention handles from a comment body', function () {
    $handles = MentionParser::handles('Hey @ada-lovelace and @Grace-Hopper, ping @ada-lovelace again.');

    expect($handles)->toBe(['ada-lovelace', 'grace-hopper']);
});

it('returns no handle when a body has no mention', function () {
    expect(MentionParser::handles('Nothing to see here.'))->toBe([]);
});

it('ignores at signs that do not start a mention', function () {
    expect(MentionParser::handles('Write to ada@example.com or mail me @ the office'))->toBe([]);
});

it('does not treat a trailing dot as part of a handle', function () {
    expect(MentionParser::handles('cc @ada-lovelace.'))->toBe(['ada-lovelace']);
});

it('derives a mention handle from a display name', function () {
    expect(MentionParser::handleFor('Ada Lovelace'))->toBe('ada-lovelace')
        ->and(MentionParser::handleFor('Grace Hopper'))->toBe('grace-hopper')
        ->and(MentionParser::handleFor('J. Doe'))->toBe('j-doe');
});

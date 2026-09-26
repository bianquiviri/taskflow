<?php

declare(strict_types=1);

use App\Enums\Theme;

test('themes expose the expected backing values', function () {
    expect(Theme::Light->value)->toBe('light')
        ->and(Theme::Dark->value)->toBe('dark');
});

<?php

declare(strict_types=1);

use App\Services\AppInfoService;

test('the application name comes from configuration', function () {
    $appInfo = app(AppInfoService::class);

    expect(config('app.name'))->toBe('TaskFlow')
        ->and($appInfo->version())->not->toBeNull();
});

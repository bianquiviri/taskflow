<?php

declare(strict_types=1);

use App\Services\AppInfoService;

it('returns the installed Laravel framework version', function () {
    $service = app(AppInfoService::class);

    expect($service->version())->toMatch('/^v\d+\.\d+\.\d+$/');
});

it('returns the current PHP runtime version', function () {
    $service = app(AppInfoService::class);

    expect($service->phpVersion())->toBe(PHP_VERSION);
});

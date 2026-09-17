<?php

declare(strict_types=1);

namespace App\Services;

use Composer\InstalledVersions;

final readonly class AppInfoService
{
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('laravel/framework') ?? 'unknown';
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }
}

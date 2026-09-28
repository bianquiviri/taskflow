<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Task;

arch('all enums are native PHP enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('every app class declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('actions and services stay out of the HTTP layer')
    ->expect(['App\Actions', 'App\Services'])
    ->not->toUse('App\Http');

arch('models do not depend on the HTTP layer')
    ->expect('App\Models')
    ->not->toUse('App\Http');

arch('observers never touch controllers')
    ->expect('App\Observers')
    ->not->toUse('App\Http\Controllers');

arch('policies never touch controllers')
    ->expect('App\Policies')
    ->not->toUse('App\Http\Controllers');
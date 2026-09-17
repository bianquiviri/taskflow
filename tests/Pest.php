<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
|
| Pest makes writing tests in Laravel a breeze. Register project-wide
| behaviour here so every feature test starts from a clean database.
|
*/

uses(TestCase::class, RefreshDatabase::class)
    ->in('Feature');

uses(TestCase::class)
    ->in('Unit');

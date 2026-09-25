<?php

declare(strict_types=1);

use App\Enums\TeamRole;

test('team roles expose the expected backing values', function () {
    expect(TeamRole::Owner->value)->toBe('owner')
        ->and(TeamRole::Admin->value)->toBe('admin')
        ->and(TeamRole::Member->value)->toBe('member');
});

test('team roles expose human labels', function () {
    expect(TeamRole::Owner->label())->toBe('Owner')
        ->and(TeamRole::Admin->label())->toBe('Admin')
        ->and(TeamRole::Member->label())->toBe('Member');
});

test('team roles expose a color per role', function () {
    expect(TeamRole::Owner->color())->toBe('indigo')
        ->and(TeamRole::Admin->color())->toBe('sky')
        ->and(TeamRole::Member->color())->toBe('gray');
});

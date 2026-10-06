<?php

declare(strict_types=1);

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

it('exposes labels and colors for task statuses', function () {
    expect(TaskStatus::cases())->toHaveCount(5)
        ->and(TaskStatus::Todo->label())->toBe('To Do')
        ->and(TaskStatus::InProgress->label())->toBe('In Progress')
        ->and(TaskStatus::InReview->label())->toBe('In Review')
        ->and(TaskStatus::Done->label())->toBe('Done')
        ->and(TaskStatus::Cancelled->label())->toBe('Cancelled')
        ->and(TaskStatus::Todo->color())->toBe('gray')
        ->and(TaskStatus::InProgress->color())->toBe('blue')
        ->and(TaskStatus::InReview->color())->toBe('amber')
        ->and(TaskStatus::Done->color())->toBe('green')
        ->and(TaskStatus::Cancelled->color())->toBe('red');
});

it('exposes labels and colors for task priorities', function () {
    expect(TaskPriority::cases())->toHaveCount(4)
        ->and(TaskPriority::Low->label())->toBe('Low')
        ->and(TaskPriority::Medium->label())->toBe('Medium')
        ->and(TaskPriority::High->label())->toBe('High')
        ->and(TaskPriority::Urgent->label())->toBe('Urgent')
        ->and(TaskPriority::Low->color())->toBe('gray')
        ->and(TaskPriority::Medium->color())->toBe('blue')
        ->and(TaskPriority::High->color())->toBe('amber')
        ->and(TaskPriority::Urgent->color())->toBe('red');
});

it('validates allowed task status transitions', function () {
    expect(TaskStatus::Todo->canTransitionTo(TaskStatus::Todo))->toBeTrue()
        ->and(TaskStatus::Todo->canTransitionTo(TaskStatus::InProgress))->toBeTrue()
        ->and(TaskStatus::Todo->canTransitionTo(TaskStatus::Done))->toBeFalse()
        ->and(TaskStatus::InProgress->canTransitionTo(TaskStatus::InReview))->toBeTrue()
        ->and(TaskStatus::InProgress->canTransitionTo(TaskStatus::Todo))->toBeTrue()
        ->and(TaskStatus::InReview->canTransitionTo(TaskStatus::Done))->toBeTrue()
        ->and(TaskStatus::Done->canTransitionTo(TaskStatus::InProgress))->toBeTrue()
        ->and(TaskStatus::Done->canTransitionTo(TaskStatus::Todo))->toBeFalse()
        ->and(TaskStatus::Cancelled->canTransitionTo(TaskStatus::Todo))->toBeTrue()
        ->and(TaskStatus::Cancelled->canTransitionTo(TaskStatus::Done))->toBeFalse();
});

<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::InReview => 'In Review',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'blue',
            self::InReview => 'amber',
            self::Done => 'green',
            self::Cancelled => 'red',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        if ($status === $this) {
            return true;
        }

        return in_array($status, match ($this) {
            self::Todo => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Todo, self::InReview, self::Cancelled],
            self::InReview => [self::InProgress, self::Done, self::Cancelled],
            self::Done => [self::InProgress],
            self::Cancelled => [self::Todo],
        }, strict: true);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TaskStatus;

/**
 * The status columns of a board, and of an inline status control: every status
 * with its label plus the statuses a task sitting in it may be moved to. The
 * rules come from the enum, so the UI can never offer a move the domain would
 * refuse.
 */
final readonly class TaskStatuses
{
    /**
     * @return list<array{value: string, label: string, allows: list<string>}>
     */
    public function columns(): array
    {
        $cases = TaskStatus::cases();

        return array_map(
            static fn (TaskStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'allows' => array_values(array_map(
                    static fn (TaskStatus $target): string => $target->value,
                    array_filter(
                        $cases,
                        static fn (TaskStatus $target): bool => $status->canTransitionTo($target),
                    ),
                )),
            ],
            $cases,
        );
    }
}

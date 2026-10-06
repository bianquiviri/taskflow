<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The shareable query parameters of a task listing: search, status, priority,
 * assignee, due range and page.
 */
class TaskFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'due_from' => ['sometimes', 'nullable', 'date'],
            'due_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:due_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * The validated filters, normalised for the query service and the filter
     * form so the URL, the query and the UI always agree.
     *
     * @return array{search: ?string, status: ?string, priority: ?string, assignee_id: ?int, due_from: ?string, due_to: ?string}
     */
    public function filters(): array
    {
        $filters = $this->validated();
        $assigneeId = $filters['assignee_id'] ?? null;

        return [
            'search' => $this->normalize($filters['search'] ?? null),
            'status' => $this->normalize($filters['status'] ?? null),
            'priority' => $this->normalize($filters['priority'] ?? null),
            'assignee_id' => $assigneeId === null ? null : (int) $assigneeId,
            'due_from' => $this->normalize($filters['due_from'] ?? null),
            'due_to' => $this->normalize($filters['due_to'] ?? null),
        ];
    }

    private function normalize(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class MyTaskIndexRequest extends TaskFilterRequest
{
    /**
     * The assignee of a "my tasks" listing is always the signed in user, so the
     * assignee filter is dropped from the query and the form.
     *
     * @return array{search: ?string, status: ?string, priority: ?string, assignee_id: null, due_from: ?string, due_to: ?string}
     */
    public function filters(): array
    {
        return [
            ...parent::filters(),
            'assignee_id' => null,
        ];
    }
}

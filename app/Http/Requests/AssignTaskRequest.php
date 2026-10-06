<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignee_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}

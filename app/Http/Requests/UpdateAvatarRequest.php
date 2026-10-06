<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * The largest avatar accepted, in kilobytes.
     */
    public const MAX_KILOBYTES = 2048;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}

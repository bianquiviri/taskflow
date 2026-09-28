<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreTaskFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.AttachmentRules::maxKilobytes(),
                'mimetypes:'.implode(',', AttachmentRules::mimetypes()),
            ],
        ];
    }

    /**
     * The messages spell the rules out, so the upload form can tell the user
     * what is accepted and how large a file may be.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a file to attach.',
            'file.max' => 'The file may not be larger than '.AttachmentRules::maxSizeLabel().'.',
            'file.mimetypes' => 'The file type is not allowed.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The upload that passed validation, which the rules guarantee to be a
     * single file.
     */
    public function attachment(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}

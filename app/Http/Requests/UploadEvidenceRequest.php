<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('report')) ?? false;
    }

    public function rules(): array
    {
        return [
            'evidence' => [
                'required',
                'file',
                File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(10 * 1024),
                'mimetypes:image/jpeg,image/png,application/pdf',
            ],
        ];
    }
}
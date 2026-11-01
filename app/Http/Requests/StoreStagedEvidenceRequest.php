<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreStagedEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
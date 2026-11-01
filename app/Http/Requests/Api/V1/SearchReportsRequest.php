<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SearchReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folio' => ['required', 'string', 'regex:/^SIR-\d{4}-[A-Z0-9]{8}$/'],
            'report_type_id' => ['prohibited'],
            'status' => ['prohibited'],
            'date_from' => ['prohibited'],
            'date_to' => ['prohibited'],
            'per_page' => ['prohibited'],
            'page' => ['prohibited'],
        ];
    }
}
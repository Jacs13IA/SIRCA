<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\Report::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'folio' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::exists('report_statuses', 'code')->whereNull('deleted_at')],
            'report_type_id' => ['nullable', 'integer', Rule::exists('report_types', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
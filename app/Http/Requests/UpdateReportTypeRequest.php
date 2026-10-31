<?php

namespace App\Http\Requests;

use App\Models\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('report_type')) ?? false;
    }

    public function rules(): array
    {
        $reportType = $this->route('report_type');

        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:120',
                Rule::unique('report_types', 'slug')
                    ->where('department_id', $this->input('department_id'))
                    ->ignore($reportType instanceof ReportType ? $reportType->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
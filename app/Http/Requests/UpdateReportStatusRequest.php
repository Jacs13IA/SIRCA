<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateStatus', $this->route('report')) ?? false;
    }

    public function rules(): array
    {
        return [
            'report_status_code' => ['required', 'string', Rule::exists('report_statuses', 'code')->whereNull('deleted_at')],
            'description' => ['required', 'string', 'max:3000'],
        ];
    }
}
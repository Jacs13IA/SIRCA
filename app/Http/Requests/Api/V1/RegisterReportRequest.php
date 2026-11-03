<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ReportType;
use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type_id' => ['required', 'integer', Rule::exists('report_types', 'id')->whereNull('deleted_at')],
            'description' => ['required', 'string', 'max:3000'],
            'location' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'citizen' => ['required', 'array:name,email,phone,contact_consent'],
            'citizen.name' => ['required', 'string', 'max:255'],
            'citizen.email' => ['nullable', 'email', 'max:255'],
            'citizen.phone' => ['nullable', 'string', 'max:30', new ValidPhoneNumber],
            'citizen.contact_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $citizen = $this->input('citizen', []);

            if ((filled($citizen['email'] ?? null) || filled($citizen['phone'] ?? null)) && ! filter_var($citizen['contact_consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add('citizen.contact_consent', 'Se requiere consentimiento para guardar datos de contacto.');
            }

            $reportType = ReportType::query()->with('department')->find($this->input('report_type_id'));

            if ($reportType && (! $reportType->is_active || ! $reportType->department?->is_active)) {
                $validator->errors()->add('report_type_id', 'El tipo de reporte o su dirección no están activos.');
            }
        }];
    }
}

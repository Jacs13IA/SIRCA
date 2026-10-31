<?php

namespace App\Http\Requests;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('report')) ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $report = $this->route('report');
            $assignee = User::query()->with('role')->find($this->input('assigned_user_id'));

            if ($report instanceof Report && $assignee && (! $assignee->hasRole('operator') || $assignee->department_id !== $report->department_id)) {
                $validator->errors()->add('assigned_user_id', 'Selecciona un operador de la misma dirección.');
            }
        }];
    }
}
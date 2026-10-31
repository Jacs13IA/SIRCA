<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('addActivity', $this->route('report')) ?? false;
    }

    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:3000']];
    }
}
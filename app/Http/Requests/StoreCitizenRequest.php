<?php

namespace App\Http\Requests;

use App\Models\Citizen;
use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCitizenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Citizen::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:320'],
            'phone' => ['nullable', 'string', 'max:50', new ValidPhoneNumber],
            'contact_consent' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (($this->filled('email') || $this->filled('phone')) && ! $this->boolean('contact_consent')) {
                $validator->errors()->add('contact_consent', 'Se requiere consentimiento para registrar datos de contacto.');
            }
        }];
    }
}

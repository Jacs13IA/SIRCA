<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->numbers()],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereIn('slug', ['administrator', 'supervisor', 'operator'])->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $role = Role::query()->find($this->input('role_id'));
            if ($role?->slug !== 'administrator' && blank($this->input('department_id'))) {
                $validator->errors()->add('department_id', 'Selecciona la dirección del usuario.');
            }

            if ($role?->slug === 'administrator' && filled($this->input('department_id'))) {
                $validator->errors()->add('department_id', 'El administrador general no se limita a una dirección.');
            }
        }];
    }
}
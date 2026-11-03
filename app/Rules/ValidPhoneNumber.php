<?php

namespace App\Rules;

use App\Services\CitizenIdentityService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class ValidPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(CitizenIdentityService::class)->normalizePhone((string) $value);
        } catch (InvalidArgumentException) {
            $fail('Ingresa un número telefónico válido con código de país si no es de México.');
        }
    }
}

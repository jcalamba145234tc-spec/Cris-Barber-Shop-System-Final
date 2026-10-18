<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ShopEmailDomain implements ValidationRule
{
    public function __construct(private readonly ?string $existingEmail = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Keep existing logins usable; only new addresses must use the shop domain.
        if ($this->existingEmail !== null && $value === $this->existingEmail) {
            return;
        }

        if (! is_string($value) || strtolower(substr($value, strrpos($value, '@') + 1)) !== 'crisbarber.com') {
            $fail('The email address must use @crisbarber.com (for example, name@crisbarber.com).');
        }
    }
}

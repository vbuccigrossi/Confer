<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class StrongPassword implements Rule
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        // Minimum 12 characters
        if (strlen($value) < 12) {
            return false;
        }

        // Must contain at least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            return false;
        }

        // Must contain at least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            return false;
        }

        // Must contain at least one number
        if (!preg_match('/[0-9]/', $value)) {
            return false;
        }

        // Must contain at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must be at least 12 characters and contain uppercase, lowercase, numbers, and special characters.';
    }
}

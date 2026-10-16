<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class WholeQuantity
{
    // Stored decimal values ending in .000 are integral, not fractional.
    public static function isWhole(mixed $value, bool $signed = false): bool
    {
        return preg_match($signed ? '/^-?\d+(?:\.0+)?$/D' : '/^\d+(?:\.0+)?$/D', (string) $value) === 1
            && abs((int) $value) <= 999999999;
    }

    public static function require(mixed $value, string $field = 'stock'): int
    {
        if (! self::isWhole($value)) {
            throw ValidationException::withMessages([$field => 'Legacy fractional, negative or oversized quantity requires owner reconciliation. Nothing was rounded or changed.']);
        }

        return (int) $value;
    }

    public static function text(mixed $value): string
    {
        $text = (string) $value;
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text;
    }

    public static function display(mixed $value): string
    {
        return self::text($value).(self::isWhole($value, true) ? '' : ' (legacy — review required)');
    }
}

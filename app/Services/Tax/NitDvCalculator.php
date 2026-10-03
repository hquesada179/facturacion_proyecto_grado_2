<?php

namespace App\Services\Tax;

use InvalidArgumentException;

/**
 * Computes and verifies the Colombian NIT check digit ("dígito de
 * verificación") using the standard modulo-11 algorithm with the fixed
 * weight table published for RUT/NIT validation. Used for both the
 * company's own NIT and customer identification numbers of type NIT —
 * the digit is always recalculated server-side, never trusted from input.
 */
class NitDvCalculator
{
    /**
     * Weight for each digit position, counted from the rightmost (units)
     * digit outward. Supports NITs of up to 15 digits.
     */
    private const WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    public static function calculate(string $nit): int
    {
        $digits = self::sanitize($nit);

        if ($digits === '') {
            throw new InvalidArgumentException('El NIT debe contener al menos un dígito numérico.');
        }

        if (strlen($digits) > count(self::WEIGHTS)) {
            throw new InvalidArgumentException('El NIT excede la longitud máxima soportada (15 dígitos).');
        }

        $reversed = array_reverse(str_split($digits));

        $sum = 0;

        foreach ($reversed as $position => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$position];
        }

        $remainder = $sum % 11;

        return $remainder <= 1 ? $remainder : 11 - $remainder;
    }

    public static function isValid(string $nit, int|string $dv): bool
    {
        return self::calculate($nit) === (int) $dv;
    }

    public static function sanitize(string $nit): string
    {
        return preg_replace('/\D/', '', $nit) ?? '';
    }
}

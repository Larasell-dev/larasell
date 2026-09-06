<?php

namespace Larasell\Larasell\Measurements;

use InvalidArgumentException;

/** @internal */
final class Decimal
{
    public const SCALE = 12;

    /**
     * @return numeric-string
     */
    public static function normalize(int|string $amount): string
    {
        if (is_int($amount)) {
            if ($amount < 0) {
                throw new InvalidArgumentException('Measurement amounts cannot be negative.');
            }

            return (string) $amount;
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Measurement amounts must be non-negative decimal numbers.');
        }

        return self::trim($amount);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function add(string $left, string $right): string
    {
        return self::trim(bcadd($left, $right, self::SCALE));
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function subtract(string $left, string $right): string
    {
        $result = bcsub($left, $right, self::SCALE);

        if (bccomp($result, '0', self::SCALE) === -1) {
            throw new InvalidArgumentException('Measurement amounts cannot be negative.');
        }

        return self::trim($result);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function multiply(string $left, string $right): string
    {
        return self::trim(bcmul($left, $right, self::SCALE));
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function divide(string $left, string $right): string
    {
        if (bccomp($right, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        return self::trim(bcdiv($left, $right, self::SCALE));
    }

    /**
     * @return numeric-string
     */
    public static function trim(string $amount): string
    {
        if (str_contains($amount, '.')) {
            $amount = rtrim(rtrim($amount, '0'), '.');
        }

        $negative = str_starts_with($amount, '-');
        $digits = $negative ? substr($amount, 1) : $amount;

        if (str_contains($digits, '.')) {
            [$whole, $fraction] = explode('.', $digits, 2);
            $whole = ltrim($whole, '0');
            $whole = $whole === '' ? '0' : $whole;
            $normalized = $whole.'.'.$fraction;
        } else {
            $normalized = ltrim($digits, '0');
            $normalized = $normalized === '' ? '0' : $normalized;
        }

        $normalized = $negative && $normalized !== '0' ? '-'.$normalized : $normalized;

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('Measurement amount could not be normalized.');
        }

        return $normalized;
    }
}

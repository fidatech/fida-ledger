<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on strings (bcmath). Every amount and rate in the system goes
 * through here: money is never a float.
 */
class Dec
{
    /** Working precision; results are rounded to the scale that is asked for. */
    private const WORK = 16;

    /** A clean decimal string from user input or the database. Empty and null are zero. */
    public static function of(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_float($value)) {
            // only SQLite hands back a float (from SUM); MySQL returns exact decimal strings
            $value = number_format($value, 8, '.', '');
        }
        $text = str_replace([',', ' '], '', trim((string) $value));
        if (! preg_match('/^-?\d+(\.\d+)?$/', $text)) {
            throw new InvalidArgumentException('Not a decimal number: '.$text);
        }

        return $text;
    }

    public static function valid(mixed $value): bool
    {
        return is_scalar($value) && preg_match('/^-?\d+(\.\d+)?$/', str_replace([',', ' '], '', trim((string) $value))) === 1;
    }

    public static function add(mixed $a, mixed $b, int $scale = 4): string
    {
        return self::round(bcadd(self::of($a), self::of($b), self::WORK), $scale);
    }

    public static function sub(mixed $a, mixed $b, int $scale = 4): string
    {
        return self::round(bcsub(self::of($a), self::of($b), self::WORK), $scale);
    }

    public static function mul(mixed $a, mixed $b, int $scale = 4): string
    {
        return self::round(bcmul(self::of($a), self::of($b), self::WORK), $scale);
    }

    public static function div(mixed $a, mixed $b, int $scale = 4): string
    {
        if (self::isZero($b)) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return self::round(bcdiv(self::of($a), self::of($b), self::WORK), $scale);
    }

    /** -1, 0 or 1. */
    public static function cmp(mixed $a, mixed $b): int
    {
        return bccomp(self::of($a), self::of($b), self::WORK);
    }

    public static function isZero(mixed $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(mixed $a): bool
    {
        return self::cmp($a, '0') === 1;
    }

    public static function isNegative(mixed $a): bool
    {
        return self::cmp($a, '0') === -1;
    }

    public static function neg(mixed $a, int $scale = 4): string
    {
        return self::round(bcmul(self::of($a), '-1', self::WORK), $scale);
    }

    public static function abs(mixed $a, int $scale = 4): string
    {
        return self::isNegative($a) ? self::neg($a, $scale) : self::round($a, $scale);
    }

    /** Sum of a list of amounts. */
    public static function sum(iterable $values, int $scale = 4): string
    {
        $total = '0';
        foreach ($values as $value) {
            $total = bcadd($total, self::of($value), self::WORK);
        }

        return self::round($total, $scale);
    }

    /** Round half away from zero (1.005 becomes 1.01, -1.005 becomes -1.01). */
    public static function round(mixed $value, int $scale = 4): string
    {
        $number = self::of($value);
        $half = '0.'.str_repeat('0', $scale).'5';
        $rounded = bccomp($number, '0', self::WORK) >= 0
            ? bcadd($number, $half, $scale)
            : bcsub($number, $half, $scale);

        // "-0.00" is zero
        return bccomp($rounded, '0', $scale) === 0 ? bcadd('0', '0', $scale) : $rounded;
    }

    /** For people: "1,234.50". Only the display is rounded; the stored amount keeps its decimals. */
    public static function format(mixed $value, int $decimals = 2): string
    {
        $rounded = self::round($value, $decimals);
        $negative = str_starts_with($rounded, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($rounded, '-'), 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return ($negative ? '-' : '').$whole.($decimals > 0 ? '.'.$fraction : '');
    }
}

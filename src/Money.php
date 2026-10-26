<?php

declare(strict_types=1);

namespace ExactLedger;

/**
 * Exact decimal money arithmetic for a double-entry ledger.
 *
 * Doing ledger arithmetic in floats works until it doesn't: accumulation across many
 * lines drifts, and the failure mode is the worst kind for accounting software — a
 * reconciliation off by 0.0001 that nobody can explain, in books that look fine.
 *
 * Everything here works in **scaled integers** at 4 decimal places, so 123.45 is
 * 1_234_500. Integers add and compare exactly, so "debits equal credits" becomes a real
 * equality instead of a tolerance. 64-bit integers hold ±922 trillion at this scale.
 */
final class Money
{
    /** Decimal places the ledger keeps (matches a decimal(15,4) column). */
    public const SCALE = 4;

    private const FACTOR = 10 ** self::SCALE;

    /**
     * Currencies whose precision is NOT 2 (ISO 4217 minor units). Precision is a property
     * of the currency, not of the display code: JPY has no minor unit, KWD has three.
     */
    public const ISO_DECIMALS = [
        0 => ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'HUF', 'TWD', 'UGX', 'RWF', 'XOF', 'XAF', 'PYG', 'DJF', 'GNF', 'KMF', 'VUV'],
        3 => ['KWD', 'BHD', 'OMR', 'TND', 'JOD', 'LYD', 'IQD'],
    ];

    /**
     * A value from anywhere (float, decimal string, int) as a scaled integer.
     *
     * Strings are parsed digit by digit rather than cast to float, because the whole point
     * is never to let a float hold the value: `(int) (0.29 * 100)` is 28, not 29.
     */
    public static function scale(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value * self::FACTOR;
        }

        $text = is_string($value) ? trim($value) : self::floatToDecimalString($value);

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $text, $m)) {
            throw new \InvalidArgumentException("Not a plain decimal amount: {$text}");
        }

        $sign = $m[1] === '-' ? -1 : 1;
        $whole = $m[2] === '' ? '0' : $m[2];
        $frac = $m[3] ?? '';

        // Round half-up on the first dropped digit rather than truncating, so a value with
        // more precision than the ledger keeps behaves like money, not like a cast.
        $keep = str_pad(substr($frac, 0, self::SCALE), self::SCALE, '0');
        $next = (int) (substr($frac, self::SCALE, 1) ?: '0');

        $scaled = (int) $whole * self::FACTOR + (int) $keep;
        if ($next >= 5) {
            $scaled++;
        }

        return $sign * $scaled;
    }

    /** @param  array<int, int|float|string|null>  $values */
    public static function sum(array $values): int
    {
        return array_sum(array_map(self::scale(...), $values));
    }

    /** A scaled integer back to the decimal string a decimal(15,4) column expects. */
    public static function toString(int $scaled): string
    {
        $sign = $scaled < 0 ? '-' : '';
        $abs = abs($scaled);

        return $sign.intdiv($abs, self::FACTOR).'.'.str_pad((string) ($abs % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT);
    }

    /** Are these two amounts the same to the ledger's precision? Exact, no epsilon. */
    public static function equals(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::scale($a) === self::scale($b);
    }

    /** How many decimals a currency has; ordinary currencies have 2. */
    public static function decimalsFor(string $currencyCode): int
    {
        $code = strtoupper(trim($currencyCode));
        foreach (self::ISO_DECIMALS as $places => $codes) {
            if (in_array($code, $codes, true)) {
                return $places;
            }
        }

        return 2;
    }

    /** An amount rendered at the precision of the currency it is in (¥12,345, not ¥12,345.00). */
    public static function format(int|float|string|null $amount, string $currencyCode): string
    {
        return number_format(self::scale($amount) / self::FACTOR, self::decimalsFor($currencyCode));
    }

    private static function floatToDecimalString(float $value): string
    {
        return rtrim(rtrim(number_format($value, self::SCALE + 2, '.', ''), '0'), '.') ?: '0';
    }
}

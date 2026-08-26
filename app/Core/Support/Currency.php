<?php

namespace App\Core\Support;

use App\Core\Services\SettingsService;

/**
 * The currencies the store can trade in, from config/currencies.php.
 *
 * Which one is active is a setting rather than configuration; this class only
 * resolves a code to how it should be written. An unknown code — an old record
 * from before a currency was removed from the list — resolves to itself rather
 * than throwing, so history stays readable.
 */
final class Currency
{
    /**
     * @return array<string, array{code: string, name: string, symbol: string, position: string, decimals: int}>
     */
    public static function all(): array
    {
        return (array) config('currencies.available', []);
    }

    /**
     * @return array<int, array{code: string, name: string, symbol: string, position: string, decimals: int}>
     */
    public static function options(): array
    {
        return array_values(self::all());
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * The store's currency, as saved in Settings.
     */
    public static function code(): string
    {
        return app(SettingsService::class)->currencyCode();
    }

    /**
     * How to write the given code, defaulting to the store's own currency —
     * not the configured one, which is only the fallback until an operator
     * chooses. An unrecognised code is written as itself rather than throwing,
     * so nothing breaks if one is removed from configuration later.
     *
     * @return array{code: string, name: string, symbol: string, position: string, decimals: int}
     */
    public static function resolve(?string $code = null): array
    {
        $code = strtoupper((string) ($code ?: self::code()));

        return self::all()[$code] ?? [
            'code' => $code,
            'name' => $code,
            'symbol' => $code,
            'position' => 'before',
            'decimals' => 2,
        ];
    }

    public static function supports(?string $code): bool
    {
        return $code !== null && array_key_exists(strtoupper($code), self::all());
    }

    /**
     * Write an amount the way the given currency is written.
     */
    public static function format(int|float|string|null $amount, ?string $code = null): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $currency = self::resolve($code);
        $formatted = number_format((float) $amount, $currency['decimals']);

        return $currency['position'] === 'after'
            ? $formatted.' '.$currency['symbol']
            : $currency['symbol'].$formatted;
    }
}

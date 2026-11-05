<?php

namespace Larasell\Larasell\SalesChannels;

use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Models\SalesChannelEntryPoint;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Settings\LocaleSettings;

/** The checks the sales channel actions run before they write. */
final readonly class ChannelRules
{
    public function __construct(
        private LocaleSettings $locales,
        private CurrencySettings $currencies,
    ) {}

    public function locale(mixed $tag): Locale
    {
        try {
            $locale = $tag instanceof Locale ? $tag : (is_string($tag) ? Locale::tryFrom(Locale::canonicalize($tag)) : null);
        } catch (InvalidArgumentException) {
            $locale = null;
        }

        if ($locale === null || ! in_array($locale, $this->locales->supported(), true)) {
            throw new InvalidSalesChannel('['.(is_string($tag) ? $tag : get_debug_type($tag)).'] is not a supported locale; add it to larasell.locale.supported first.');
        }

        return $locale;
    }

    public function currency(mixed $code): Currency
    {
        $currency = $code instanceof Currency ? $code : (is_string($code) ? Currency::tryFrom(strtoupper($code)) : null);

        if ($currency === null || ! in_array($currency, $this->currencies->enabled(), true)) {
            throw new InvalidSalesChannel('The currency ['.(is_string($code) ? $code : get_debug_type($code)).'] is not enabled in the currency settings.');
        }

        return $currency;
    }

    /**
     * @param  list<Locale>  $locales
     * @param  list<Currency>  $currencies
     */
    public function defaults(array $locales, array $currencies, Locale $locale, Currency $currency): void
    {
        if (! in_array($locale, $locales, true)) {
            throw new InvalidSalesChannel("The default locale [{$locale->value}] must be one of the channel's locales.");
        }

        if (! in_array($currency, $currencies, true)) {
            throw new InvalidSalesChannel("The default currency [{$currency->value}] must be one of the channel's currencies.");
        }
    }

    /**
     * The scheme, host, port and prefix of an entry point URL, checked against the channel and every other entry point.
     *
     * @param  list<Locale>  $locales
     * @param  list<Currency>  $currencies
     * @return array{scheme: string, host: string, port: int|null, prefix: string}
     */
    public function entryPoint(string $url, Locale $locale, Currency $currency, array $locales, array $currencies): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidSalesChannel("The entry point URL [{$url}] needs a scheme and a host.");
        }

        if (! in_array($locale, $locales, true)) {
            throw new InvalidSalesChannel("The entry point [{$url}] serves the locale [{$locale->value}], which its channel does not have.");
        }

        if (! in_array($currency, $currencies, true)) {
            throw new InvalidSalesChannel("The entry point [{$url}] uses the currency [{$currency->value}], which its channel does not have.");
        }

        $entryPoint = [
            'scheme' => strtolower($parts['scheme']),
            'host' => strtolower($parts['host']),
            'port' => $parts['port'] ?? null,
            'prefix' => trim($parts['path'] ?? '', '/'),
        ];

        if (SalesChannelEntryPoint::query()->where('host', $entryPoint['host'])->where('prefix', $entryPoint['prefix'])->exists()) {
            throw new InvalidSalesChannel("The entry point [{$url}] is already used by a sales channel.");
        }

        return $entryPoint;
    }
}

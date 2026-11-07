<?php

namespace Larasell\Larasell\SalesChannels;

use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Settings\LocaleSettings;

/** The checks the sales channel actions run before they write. */
final readonly class ChannelRules
{
    public function __construct(
        private LocaleSettings $locales,
        private CurrencySettings $currencies,
        private SalesChannelTypes $types,
    ) {}

    /** Code names a channel by its key, e.g. SalesContext::for('b2b-shop'), so it is lowercase words joined by - or _, and unique. */
    public function key(string $key): string
    {
        if (strlen($key) > 64) {
            throw new InvalidSalesChannel("The sales channel key [{$key}] is longer than 64 characters.");
        }

        if (preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidSalesChannel("The sales channel key [{$key}] must be lowercase letters and numbers, joined by - or _.");
        }

        if (SalesChannel::query()->where('key', $key)->exists()) {
            throw new InvalidSalesChannel("There is already a sales channel [{$key}].");
        }

        return $key;
    }

    public function type(string $type): string
    {
        if (! $this->types->has($type)) {
            throw new InvalidSalesChannel("There is no sales channel type [{$type}]; known are: ".implode(', ', $this->types->all()).'.');
        }

        return $type;
    }

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
}

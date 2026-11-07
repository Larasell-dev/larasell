<?php

namespace Larasell\Larasell\SalesChannels\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/** A sales channel, its context or one of its entry points was asked for something it does not allow. */
final class InvalidSalesChannel extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function keyTooLong(string $key): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.key-too-long',
            message: "The sales channel key [{$key}] is longer than 64 characters.",
            why: 'The key is stored in a column of 64 characters.',
            fix: 'Pick a shorter key, e.g. b2b-shop.',
        );
    }

    public static function malformedKey(string $key): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.malformed-key',
            message: "The sales channel key [{$key}] must be lowercase letters and numbers, joined by - or _.",
            why: "Code names a channel by its key, e.g. SalesContext::for('b2b-shop'), so it has to stay simple to type.",
            fix: 'Use lowercase letters and numbers joined by - or _, e.g. b2b-shop.',
        );
    }

    public static function keyTaken(string $key): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.key-taken',
            message: "There is already a sales channel [{$key}].",
            why: 'A key names exactly one channel.',
            fix: 'Pick another key, or update the existing channel.',
        );
    }

    /** @param  list<string>  $known */
    public static function unknownType(string $type, array $known): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.unknown-type',
            message: "There is no sales channel type [{$type}]; known are: ".implode(', ', $known).'.',
            why: 'Only registered types know how their channels are served.',
            fix: 'Use one of the known types, or register the type in SalesChannelTypes first.',
        );
    }

    public static function unsupportedLocale(string $tag): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.unsupported-locale',
            message: "[{$tag}] is not a supported locale; add it to larasell.locale.supported first.",
            why: 'A channel can only sell in locales the shop supports.',
            fix: 'Add the locale to larasell.locale.supported, or pick a supported one.',
        );
    }

    public static function currencyNotEnabled(string $code): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.currency-not-enabled',
            message: "The currency [{$code}] is not enabled in the currency settings.",
            why: 'A channel can only sell in currencies the shop has enabled.',
            fix: 'Enable the currency in the currency settings, or pick an enabled one.',
        );
    }

    public static function defaultLocaleNotInChannel(string $locale): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.default-locale-missing',
            message: "The default locale [{$locale}] must be one of the channel's locales.",
            why: 'Visits without a locale start in the default one, so the channel has to sell in it.',
            fix: "Add the locale to the channel's locales, or pick one of them as the default.",
        );
    }

    public static function defaultCurrencyNotInChannel(string $currency): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.default-currency-missing',
            message: "The default currency [{$currency}] must be one of the channel's currencies.",
            why: 'Visits without a currency start in the default one, so the channel has to sell in it.',
            fix: "Add the currency to the channel's currencies, or pick one of them as the default.",
        );
    }

    public static function unknown(string $channel): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.unknown',
            message: "There is no sales channel [{$channel}].",
            why: 'No channel has this key or ID; it may have been deleted or misspelled.',
            fix: 'Check the key or ID, or create the channel first.',
        );
    }

    public static function doesNotSellIn(string $channel, string $localeOrCurrency): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.not-sold-in',
            message: "The sales channel [{$channel}] does not sell in [{$localeOrCurrency}].",
            why: 'A sales context can only use the locales and currencies of its channel.',
            fix: 'Add it to the channel, or use one of the locales and currencies it sells in.',
        );
    }

    public static function lastOne(string $channel): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.last-one',
            message: "The sales channel [{$channel}] is the last one, so it cannot be deleted.",
            why: 'Every cart and order is made in a sales channel, so the shop needs at least one.',
            fix: 'Create another channel first, or deactivate this one.',
        );
    }

    public static function notAStorefront(string $channel): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.not-a-storefront',
            message: "The sales channel [{$channel}] is not a storefront, so it has no entry points.",
            why: 'Only storefront channels are visited through URLs.',
            fix: 'Add the entry point to a storefront channel.',
        );
    }

    public static function entryPointWithoutHost(string $url): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.entry-point-without-host',
            message: "The entry point URL [{$url}] needs a scheme and a host.",
            why: 'Visits are matched to entry points by their host.',
            fix: 'Pass a full URL, e.g. https://shop.example.com/de.',
        );
    }

    public static function entryPointLocaleMissing(string $url, string $locale): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.entry-point-locale-missing',
            message: "The entry point [{$url}] serves the locale [{$locale}], which its channel does not have.",
            why: 'An entry point starts visits in its channel, so the channel has to sell in its locale.',
            fix: "Add the locale to the channel first, or pick one of the channel's locales.",
        );
    }

    public static function entryPointCurrencyMissing(string $url, string $currency): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.entry-point-currency-missing',
            message: "The entry point [{$url}] starts in the currency [{$currency}], which its channel does not have.",
            why: 'An entry point starts visits in its channel, so the channel has to sell in its currency.',
            fix: "Add the currency to the channel first, or pick one of the channel's currencies.",
        );
    }

    public static function entryPointTaken(string $url): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.entry-point-taken',
            message: "The entry point [{$url}] is already used by a sales channel.",
            why: 'A visit to the URL has to lead to exactly one channel.',
            fix: 'Pick another host or path prefix, or delete the existing entry point first.',
        );
    }

    public static function entryPointStillServesLocale(string $url, string $locale): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.locale-in-use',
            message: "The entry point [{$url}] still serves [{$locale}]; delete it first.",
            why: 'Removing the locale from the channel would leave the entry point serving a locale the channel does not sell in.',
            fix: 'Delete the entry point first, then remove the locale.',
        );
    }

    public static function entryPointStillStartsIn(string $url, string $currency): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.currency-in-use',
            message: "The entry point [{$url}] still starts visits in [{$currency}]; delete it first.",
            why: 'Removing the currency from the channel would leave the entry point starting visits in a currency the channel does not sell in.',
            fix: 'Delete the entry point first, then remove the currency.',
        );
    }
}

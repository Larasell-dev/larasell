# Entry points serve a locale; the currency comes from the visit

An entry point (a host plus an optional path prefix) now serves one locale,
not one locale and one currency. Tying a URL to a currency meant a shop
selling in EUR and CHF needed a domain per currency, which search engines see
as duplicate pages. A visit starts in the entry point's optional
`default_currency`, else the channel's default, and can switch to any other
currency the channel sells in through the same URL.

## Who is affected

Apps that call `AddEntryPoint`, read `EntryPoint::$currency` or the
`currency` column of `larasell_storefront_entry_points`, or listen to
`EntryPointCreated`. Apps that only use `StorefrontContext` are not affected.

## How to upgrade

| Before | After |
| --- | --- |
| `AddEntryPoint` input `currency` | `default_currency` (optional) |
| `EntryPoint::$currency`, column `currency` | `EntryPoint::$defaultCurrency`, nullable column `default_currency` |
| `EntryPointCreated::$currency` | `EntryPointCreated::$defaultCurrency` (nullable) |
| one primary entry point per channel, locale and currency | one primary entry point per channel and locale |
| a currency needs its own entry point | `$storefront->withCurrency(Currency::EUR)` on the same entry point |

```php
// Before
$addEntryPoint->handle($channelId, ['url' => 'https://shop.ch', 'locale' => 'de', 'currency' => 'CHF']);

// After
$addEntryPoint->handle($channelId, ['url' => 'https://shop.ch', 'locale' => 'de', 'default_currency' => 'CHF']);
$storefront = $storefront->withCurrency(Currency::EUR); // the visitor picked EUR
```

Where the visitor's pick comes from, such as a switcher or their cart's
`currency`, is up to the app: call `withCurrency()` with it.

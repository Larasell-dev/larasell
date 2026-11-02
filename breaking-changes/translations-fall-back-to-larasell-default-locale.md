# Translations fall back to `larasell.locale.default`, not `app.fallback_locale`

When a translation is missing in the requested locale, `Translatable::get()` now
falls back to the shop's default locale from the new
`config/larasell/locale.php`. Plain string values read from the database are
stored under that locale too. This gives a shop one setting for its default
language, next to the list of locales it supports.

## Who is affected

Apps that set `app.fallback_locale` to something other than `en`. Apps whose
`app.fallback_locale` is `en` are not affected, because
`larasell.locale.default` also defaults to `en`.

## How to upgrade

Publish the config and set the default to your previous `app.fallback_locale`.
The default must also be in `supported`.

```bash
php artisan vendor:publish --tag=larasell-config
```

```php
// Before: config/app.php
'fallback_locale' => 'de',

// After: config/larasell/locale.php
use Larasell\Larasell\Enums\Locale;

return [
    'supported' => [Locale::GERMAN, Locale::ENGLISH],
    'default' => Locale::GERMAN,
];
```

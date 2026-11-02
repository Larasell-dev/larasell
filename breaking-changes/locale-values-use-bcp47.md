# `Locale` values are now BCP 47 tags (`de-DE` instead of `de_DE`)

Every value of `Larasell\Larasell\Enums\Locale` that has a region or script now
uses a hyphen, as in `de-DE`, `zh-Hans` and `es-419`. Translation keys are stored
the same way. This is the format HTTP headers, HTML `lang` and `hreflang` use, so
no conversion is needed at the HTTP boundary. Script locales like `zh-Hans`,
which `Translatable` used to reject, now work.

## Who is affected

- Code that stores, compares or hard-codes `Locale->value`, or calls
  `Locale::from()` with an underscore value such as `'de_DE'`.
- Code that reads translation JSON (`name`, `slug`, `description`,
  `product_name`) directly from the database, or queries it with paths like
  `slug->de_DE`.

Apps that only use base locales such as `en` and `de` are not affected: their
values did not change. `App::setLocale('de_DE')` keeps working, and
`Translatable` accepts underscore keys and stores them as hyphens.

## How to upgrade

Run the migration. It renames stored translation keys from `de_DE` to `de-DE`
and can be rolled back.

```bash
php artisan migrate
```

Replace underscore values in your code, or canonicalize input first:

```php
use Larasell\Larasell\Enums\Locale;

// Before
Locale::from('de_DE');
$query->where('slug->de_DE', $slug);

// After
Locale::from('de-DE');
Locale::from(Locale::canonicalize('de_DE')); // for input in any case or style
$query->where('slug->de-DE', $slug);
```

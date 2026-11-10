# `Product::$description` is now rich text

A product's description is now formatted text in Larasell's own JSON format, one document per
locale. It used to be a plain `Translatable` string, which can't hold headings, lists or links,
and HTML stored instead would be unsafe for storefronts to print.

## Who is affected

Apps that read or write `Product::$description`. Apps that never touch the description are not affected.

Existing descriptions need no migration: stored plain text reads as one paragraph per line, and
setting a string, an array of strings per locale or a `Translatable` still works.

## How to upgrade

`$product->description` is now a `Larasell\Larasell\RichText\TranslatableRichText` (or `null`),
and `get()` returns a `Larasell\Larasell\RichText\RichText`. Render it with `toHtml()`, which
escapes every text, or read it with `toPlainText()`, e.g. for a meta description.

```php
// Before
{{ $product->description?->get() }}

// After
{!! $product->description?->get()->toHtml() !!}
```

```php
// Before
$product->description->with('de', 'Licht.');

// After
use Larasell\Larasell\RichText\RichText;

$product->description->with('de', RichText::fromStored('Licht.'));
```

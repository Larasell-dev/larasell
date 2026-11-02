# Product attribute and value names are now `Translatable`

`ProductAttribute::$name` and `ProductAttributeValue::$name` are now
`Larasell\Larasell\Translatable` instances instead of strings, like product and
category names. Variant options such as "Color" and "Red" can now be shown in
the visitor's language.

## Who is affected

Code that reads `name` on a `ProductAttribute` or `ProductAttributeValue` and
treats it as a string, for example by printing it, comparing it, or calling
`pluck('name')`.

`ProductVariant::options()` and `ProductVariant::name()` keep returning strings,
now in the current locale. Code that only uses them is not affected. Writing
plain strings keeps working: they are stored for the current locale.

## How to upgrade

Run the migrations. They turn every existing name into a translation for
`larasell.locale.default`.

```bash
php artisan migrate
```

Call `get()` where you need the text:

```php
// Before
$attribute->name; // 'Color'
$attribute->values()->pluck('name');

// After
$attribute->name->get(); // 'Color', or 'Farbe' when the locale is 'de'
$attribute->values->map(fn ($value) => $value->name->get());
```

Translate names the same way as product names:

```php
use Larasell\Larasell\Models\ProductAttribute;

ProductAttribute::create(['slug' => 'color', 'name' => ['en' => 'Color', 'de' => 'Farbe']]);
```

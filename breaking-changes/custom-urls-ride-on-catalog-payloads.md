# Custom URLs are attachments on the catalog payloads, and the `*WithUrls` workflows are gone

`CreateCategoryWithUrls`, `UpdateCategoryWithUrls`, `CreateProductWithUrls` and
`UpdateProductWithUrls` have been removed. Instead, pass a `Larasell\Larasell\Seo\Catalog\CustomUrls`
attachment to the catalog action. The SEO module listens to the catalog events and saves the URLs
inside the action's transaction, so a taken path still undoes the catalog write. Apps no longer need
a second entry point for every catalog action.

`CreateProduct` and `UpdateProduct` now take `CreateProductPayload` and `UpdateProductPayload`
instead of an attributes array. `ProductCreated` and `ProductUpdated` carry the payload's
`Attachments`. `ProductUpdated` now also fires when only attachments were passed, with
`$changed === []`.

## Who is affected

- Apps that call any of the four `*WithUrls` workflows.
- Apps that call `CreateProduct` or `UpdateProduct` directly.
- Apps with `ProductUpdated` listeners that assume at least one attribute changed.

`DiscontinueProduct` is unchanged. Apps that only listen to `ProductCreated` are not affected.

## How to upgrade

```php
// Before
use Larasell\Larasell\Workflows\CreateCategoryWithUrls;
use Larasell\Larasell\Workflows\UpdateProductWithUrls;

app(CreateProduct::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
app(UpdateProduct::class)->handle(ProductId::of($boots), ['sku' => 'B-2']);
app(CreateCategoryWithUrls::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Shoes'])), ['en' => 'footwear']);
app(UpdateProductWithUrls::class)->handle(ProductId::of($boots), ['name' => ['en' => 'Leather Boots']], ['de' => 'winter-angebot', 'en' => null]);

// After
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Seo\Catalog\CustomUrls;

app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]));
app(UpdateProduct::class)->handle(ProductId::of($boots), new UpdateProductPayload(['sku' => 'B-2']));
app(CreateCategory::class)->handle(new CreateCategoryPayload(
    new Translatable(['en' => 'Shoes']),
    attachments: new Attachments(new CustomUrls(['en' => 'footwear'])),
));
app(UpdateProduct::class)->handle(ProductId::of($boots), new UpdateProductPayload(
    ['name' => ['en' => 'Leather Boots']],
    new Attachments(new CustomUrls(['de' => 'winter-angebot', 'en' => null])),
));
```

`new CustomUrls()` throws an `InvalidArgumentException` for a locale no sales channel serves, so
nothing is written. A null path still goes back to the automatic URL.

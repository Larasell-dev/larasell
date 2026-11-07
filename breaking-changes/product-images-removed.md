# Product images are gone from the core

The core no longer stores product images. They will come back in a dedicated media module,
so the old `ProductImage` model and its placeholder system are removed instead of kept alongside.

## Who is affected

Apps that use any of these: `ProductImage`, `Product::images()`, `Product::$thumbnail`,
`ProductVariant::$thumbnail`, `CreateProductImage`, `UpdateProductImage`, `DeleteProductImage`,
`SetProductImages`, the `ProductImageCreated`/`ProductImageUpdated`/`ProductImageDeleted` events,
`ImageId`, `ProductImageFileCast`, `PlaceholderCast`, `PlaceholderGenerator` and everything in
`Larasell\Larasell\Images`, the `larasell:refresh-placeholders` command, or the `larasell.images`
config. `ProductUpdated::$changed` no longer contains `"images"`.

A new migration drops the `larasell_product_images` and `larasell_product_product_image` tables.
**Their rows are deleted**; the files on the images disk are left in place.

## How to upgrade

Back up the two tables first if you need the data, remove any code using the classes above,
remove `images` from a published `config/larasell.php`, and run the migrations:

```sh
php artisan migrate
```

# Larasell's models can no longer be swapped

The `larasell.models` and `larasell.seo.models` config keys are gone, together
with `Larasell\Larasell\Models\ModelRegistry` and `ModelEntry`. Core code now
always uses its own models, such as `Product` and `Order`.

Swapping a model let an app change core behaviour from the outside, and every
module had to look its models up at runtime. That made identities unstable (a
swapped product got new SEO URLs) and made module boundaries impossible to
check. Apps now keep their own data in their own models and link them to core
records by ID.

## Who is affected

Apps that set `larasell.models.*` or `larasell.seo.models.seo_url` in
`config/larasell.php` or `config/larasell/seo.php`, or that resolve
`ModelRegistry` from the container. Apps that never swapped a model are not
affected; you may delete the `models` section from published config files.

## How to upgrade

Remove the `models` section from your published config. Move the fields and
relations your subclass added into a model of your own with a foreign key to
the core table:

```php
// Before: config/larasell.php
'models' => [
    'product' => App\Models\Product::class, // extends Larasell\Larasell\Models\Product
],

// After: a model of your own, linked by product ID
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Catalog\Models\Product;

class ProductDetails extends Model
{
    protected $fillable = ['product_id', 'care_instructions'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

// Read it next to the product
$details = ProductDetails::query()->where('product_id', $product->id)->first();
```

```php
// Migration for the app's own table
Schema::create('product_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->unique()->constrained('larasell_products')->cascadeOnDelete();
    $table->text('care_instructions')->nullable();
    $table->timestamps();
});
```

To react to changes of core records, listen to their domain events, such as
`Larasell\Larasell\Catalog\Events\ProductDeleted`, instead of overriding model
methods.

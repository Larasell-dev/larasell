<?php

namespace Larasell\Larasell\Catalog\Brands\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Casts\NullableTranslatableCast;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Translatable;

/**
 * The brand a product is marketed under, such as Nike. Not the legal producer: see Manufacturer.
 *
 * @property int $id
 * @property string $slug
 * @property Translatable $name
 * @property Translatable|null $description
 */
class Brand extends Model
{
    protected $table = 'larasell_brands';

    protected $guarded = [];

    protected $casts = [
        'name' => TranslatableCast::class,
        'description' => NullableTranslatableCast::class,
    ];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

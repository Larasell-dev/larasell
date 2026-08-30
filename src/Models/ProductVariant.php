<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Larasell\Larasell\Casts\NullablePriceCast;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property string|null $barcode
 * @property Price|null $price
 * @property int|null $stock
 * @property bool|null $allow_backorders
 * @property int|null $min_quantity
 * @property int|null $max_quantity
 * @property Visibility $status
 * @property int $position
 * @property string $combination_key
 * @property array<string, mixed>|null $metadata
 * @property Product $product
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $table = 'larasell_product_variants';

    protected $guarded = [];

    protected $casts = [
        'price' => NullablePriceCast::class,
        'stock' => 'integer',
        'allow_backorders' => 'boolean',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'status' => Visibility::class,
        'position' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->product->class());
    }

    /** @return BelongsToMany<ProductAttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            app(ModelRegistry::class)->productAttributeValue->class(),
            'larasell_product_variant_product_attribute_value',
            'product_variant_id',
            'product_attribute_value_id',
        );
    }
}

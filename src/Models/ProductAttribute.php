<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Enums\ProductAttributeType;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ProductAttributeType $type
 */
class ProductAttribute extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_product_attributes';

    protected $guarded = [];

    protected $casts = [
        'type' => ProductAttributeType::class,
    ];

    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(
            $this->productAttributeValueModel(),
            'product_attribute_id'
        );
    }

    /** @return class-string<ProductAttributeValue> */
    protected function productAttributeValueModel(): string
    {
        return app(ModelRegistry::class)->productAttributeValue->class();
    }
}

<?php

namespace Larasell\Larasell\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property string $slug
 * @property Translatable $name
 * @property ProductAttributeType $type
 * @property AttributeScope $scope
 */
class ProductAttribute extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_product_attributes';

    protected $guarded = [];

    protected $attributes = ['scope' => 'product'];

    protected $casts = [
        'scope' => AttributeScope::class,
        'name' => TranslatableCast::class,
        'type' => ProductAttributeType::class,
    ];

    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(
            ProductAttributeValue::class,
            'product_attribute_id'
        );
    }
}

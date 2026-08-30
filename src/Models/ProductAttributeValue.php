<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $product_attribute_id
 * @property string $slug
 * @property string $name
 * @property mixed $value
 * @property int|null $position
 * @property ProductAttribute $attribute
 */
class ProductAttributeValue extends Model
{
    use HasFactory;

    protected $table = 'larasell_product_attribute_values';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $value): void {
            $value->assertValueMatchesAttributeType();
        });
    }

    /**
     * @return Attribute<mixed, mixed>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value): mixed => $value === null ? null : json_decode($value, true),
            set: fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<ProductAttribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(
            $this->productAttributeModel(),
            'product_attribute_id'
        );
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productModel(),
            'larasell_product_product_attribute_value',
            'product_attribute_value_id',
            'product_id'
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            app(ModelRegistry::class)->productVariant->class(),
            'larasell_product_variant_product_attribute_value',
            'product_attribute_value_id',
            'product_variant_id',
        );
    }

    protected function productAttributeModel(): string
    {
        return app(ModelRegistry::class)->productAttribute->class();
    }

    protected function productModel(): string
    {
        return app(ModelRegistry::class)->product->class();
    }

    private function assertValueMatchesAttributeType(): void
    {
        $attribute = $this->relationLoaded('attribute')
            ? $this->attribute
            : $this->attribute()->first();

        if (! $attribute instanceof ProductAttribute) {
            return;
        }

        if ($attribute->type->accepts($this->value)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Product attribute value must be %s.',
            $attribute->type->value
        ));
    }
}

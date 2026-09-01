<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
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
 * @property bool $is_default
 * @property int $position
 * @property string $combination_key
 * @property array<string, mixed>|null $metadata
 * @property Product $product
 * @property Collection<int, ProductAttributeValue> $attributeValues
 */
class ProductVariant extends Model
{
    /** @use HasFactory<Factory<static>> */
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
        'is_default' => 'boolean',
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

    public function unitPrice(): Price
    {
        return $this->price ?? $this->product->price;
    }

    public function effectiveSku(): ?string
    {
        return $this->sku ?? $this->product->sku;
    }

    public function effectiveBarcode(): ?string
    {
        return $this->barcode ?? $this->product->barcode;
    }

    public function availableStock(): ?int
    {
        return $this->stock ?? $this->product->stock;
    }

    public function allowsBackorders(): bool
    {
        return $this->allow_backorders ?? $this->product->allow_backorders;
    }

    public function minimumQuantity(): ?int
    {
        return $this->min_quantity ?? $this->product->min_quantity;
    }

    public function maximumQuantity(): ?int
    {
        return $this->max_quantity ?? $this->product->max_quantity;
    }

    /**
     * @return array<string, array{attribute_id: int, attribute_slug: string, attribute_name: string, value_id: int, value_slug: string, value_name: string}>
     */
    public function optionSnapshot(): array
    {
        if ($this->is_default) {
            return [];
        }

        $positions = $this->product->variantDimensions
            ->pluck('pivot.position', 'id');

        return $this->attributeValues
            ->sortBy(fn (ProductAttributeValue $value): array => [
                $positions->get($value->product_attribute_id, PHP_INT_MAX),
                $value->product_attribute_id,
            ])
            ->mapWithKeys(fn (ProductAttributeValue $value): array => [
                $value->attribute->slug => [
                    'attribute_id' => $value->attribute->id,
                    'attribute_slug' => $value->attribute->slug,
                    'attribute_name' => $value->attribute->name,
                    'value_id' => $value->id,
                    'value_slug' => $value->slug,
                    'value_name' => $value->name,
                ],
            ])
            ->all();
    }

    public function snapshotName(): string
    {
        $options = $this->optionSnapshot();

        return $options === []
            ? $this->product->name->get()
            : collect($options)->pluck('value_name')->implode(' / ');
    }

    public function decrementInventory(int $quantity): void
    {
        if ($this->stock === null) {
            $this->product->decrement('stock', $quantity);

            return;
        }

        $this->decrement('stock', $quantity);

        if ($this->is_default) {
            $this->product->decrement('stock', $quantity);
        }
    }

    public function incrementInventory(int $quantity): void
    {
        if ($this->stock === null) {
            $this->product->increment('stock', $quantity);

            return;
        }

        $this->increment('stock', $quantity);

        if ($this->is_default) {
            $this->product->increment('stock', $quantity);
        }
    }
}

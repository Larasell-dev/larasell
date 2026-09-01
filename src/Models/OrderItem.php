<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection as SupportCollection;
use Larasell\Larasell\Casts\NullablePriceCast;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property Translatable $product_name
 * @property string|null $product_slug
 * @property string|null $product_sku
 * @property string|null $product_barcode
 * @property string|null $variant_name
 * @property array<string, mixed>|null $variant_options
 * @property Price $unit_price
 * @property int $quantity
 * @property int $inventory_quantity
 * @property Price $discount_total
 * @property Price $total
 * @property string|null $tax_category
 * @property Price|null $taxable_amount
 * @property Price|null $tax_total
 * @property array<string, mixed>|null $tax_snapshot
 * @property SupportCollection<string, mixed> $metadata
 */
class OrderItem extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_order_items';

    protected $guarded = [];

    protected $attributes = [
        'discount_total' => '{"amount":"0"}',
        'metadata' => '[]',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'product_name' => TranslatableCast::class,
        'quantity' => 'integer',
        'inventory_quantity' => 'integer',
        'metadata' => AsCollection::class,
        'variant_options' => 'array',
        'unit_price' => PriceCast::class,
        'discount_total' => PriceCast::class,
        'total' => PriceCast::class,
        'taxable_amount' => NullablePriceCast::class,
        'tax_total' => NullablePriceCast::class,
        'tax_snapshot' => 'array',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->productVariant->class(), 'product_variant_id');
    }

    /** @return HasOne<InventoryReservation, $this> */
    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(app(ModelRegistry::class)->inventoryReservation->class());
    }
}

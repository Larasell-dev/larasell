<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection as SupportCollection;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property SupportCollection<string, mixed> $metadata
 * @property Product $product
 * @property ProductVariant $variant
 */
class CartItem extends Model
{
    use HasFactory;

    protected $table = 'larasell_cart_items';

    protected $guarded = [];

    protected $attributes = [
        'metadata' => '[]',
    ];

    protected $casts = [
        'metadata' => AsCollection::class,
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo($this->cartModel());
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo($this->productModel());
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo($this->productVariantModel(), 'product_variant_id');
    }

    public function unitPrice(): Price
    {
        return $this->variant->unitPrice();
    }

    public function sku(): ?string
    {
        return $this->variant->effectiveSku();
    }

    public function barcode(): ?string
    {
        return $this->variant->effectiveBarcode();
    }

    public function availableStock(): ?int
    {
        return $this->variant->availableStock();
    }

    public function allowsBackorders(): bool
    {
        return $this->variant->allowsBackorders();
    }

    public function total(): Price
    {
        return $this->unitPrice()->multiply($this->quantity);
    }

    /** @return class-string<Cart> */
    protected function cartModel(): string
    {
        return app(ModelRegistry::class)->cart->class();
    }

    /** @return class-string<Product> */
    protected function productModel(): string
    {
        return app(ModelRegistry::class)->product->class();
    }

    /** @return class-string<ProductVariant> */
    protected function productVariantModel(): string
    {
        return app(ModelRegistry::class)->productVariant->class();
    }
}

<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int $quantity
 * @property Product $product
 */
class CartItem extends Model
{
    use HasFactory;

    protected $table = 'larasell_cart_items';

    protected $guarded = [];

    protected $casts = [
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

    public function total(): Price
    {
        return $this->product->price->multiply($this->quantity);
    }

    protected function cartModel(): string
    {
        return app(ModelRegistry::class)->cart->class();
    }

    protected function productModel(): string
    {
        return app(ModelRegistry::class)->product->class();
    }
}

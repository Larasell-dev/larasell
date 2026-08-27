<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingOption;

/**
 * @property int $id
 * @property Currency $currency
 * @property string|null $session_id
 * @property int|null $user_id
 * @property string|null $shipping_option
 * @property Collection<int, CartItem> $items
 */
class Cart extends Model
{
    use HasFactory;

    protected $table = 'larasell_carts';

    protected $guarded = [];

    protected $casts = [
        'currency' => Currency::class,
        'user_id' => 'integer',
    ];

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany($this->cartItemModel());
    }

    public function add(Product $product, int $quantity = 1): CartItem
    {
        $this->assertValidQuantity($quantity);

        $item = $this->items()
            ->where('product_id', $product->getKey())
            ->first();

        $quantity = $quantity + ($item?->quantity ?? 0);
        $this->assertProductCanBePurchased($product, $quantity);

        return $this->items()->updateOrCreate(
            ['product_id' => $product->getKey()],
            ['quantity' => $quantity]
        );
    }

    public function set(Product $product, int $quantity): CartItem
    {
        $this->assertValidQuantity($quantity);
        $this->assertProductCanBePurchased($product, $quantity);

        return $this->items()->updateOrCreate(
            ['product_id' => $product->getKey()],
            ['quantity' => $quantity]
        );
    }

    public function remove(Product $product): void
    {
        $this->items()
            ->where('product_id', $product->getKey())
            ->delete();
    }

    public function clear(): void
    {
        $this->items()->delete();
    }

    public function quantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function subtotal(): ?Price
    {
        $items = $this->items()->with('product')->get();

        if ($items->isEmpty()) {
            return null;
        }

        $total = $items->first()->total();

        foreach ($items->skip(1) as $item) {
            $total = $total->add($item->total());
        }

        return $total;
    }

    /** @return \Illuminate\Support\Collection<int, ShippingOption> */
    public function shippingOptions(): \Illuminate\Support\Collection
    {
        return app(ShippingManager::class)->options($this);
    }

    public function selectShippingOption(ShippingOption|string $option): self
    {
        $handle = $option instanceof ShippingOption ? $option->handle : $option;

        if ($this->shippingOptions()->firstWhere('handle', $handle) === null) {
            throw new InvalidArgumentException("Shipping option [{$handle}] is not available for this cart.");
        }

        $this->update(['shipping_option' => $handle]);

        return $this;
    }

    public function shippingOption(): ?ShippingOption
    {
        if ($this->shipping_option === null) {
            return null;
        }

        $option = $this->shippingOptions()->firstWhere('handle', $this->shipping_option);

        if ($option === null) {
            throw new InvalidArgumentException("Selected shipping option [{$this->shipping_option}] is no longer available for this cart.");
        }

        return $option;
    }

    public function total(): ?Price
    {
        $subtotal = $this->subtotal();

        if ($subtotal === null) {
            return null;
        }

        $shippingOption = $this->shippingOption();

        return $shippingOption === null ? $subtotal : $subtotal->add($shippingOption->price);
    }

    protected function cartItemModel(): string
    {
        return app(ModelRegistry::class)->cartItem->class();
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Cart item quantity must be at least 1.');
        }
    }

    public function assertProductCanBePurchased(Product $product, int $quantity): void
    {
        if ($product->min_quantity !== null && $quantity < $product->min_quantity) {
            throw new InvalidArgumentException('Cart item quantity is below the product minimum quantity.');
        }

        if ($product->max_quantity !== null && $quantity > $product->max_quantity) {
            throw new InvalidArgumentException('Cart item quantity exceeds the product maximum quantity.');
        }

        if (! $product->allow_backorders && $product->stock !== null && $quantity > $product->stock) {
            throw new InvalidArgumentException('Cart item quantity exceeds available product stock.');
        }
    }
}

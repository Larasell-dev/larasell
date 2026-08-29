<?php

namespace Larasell\Larasell\Models;

use Illuminate\Contracts\Config\Repository;

final readonly class ModelRegistry
{
    /** @var ModelEntry<Cart> */
    public ModelEntry $cart;

    /** @var ModelEntry<CartItem> */
    public ModelEntry $cartItem;

    /** @var ModelEntry<Category> */
    public ModelEntry $category;

    /** @var ModelEntry<InventoryReservation> */
    public ModelEntry $inventoryReservation;

    /** @var ModelEntry<Order> */
    public ModelEntry $order;

    /** @var ModelEntry<OrderItem> */
    public ModelEntry $orderItem;

    /** @var ModelEntry<Payment> */
    public ModelEntry $payment;

    /** @var ModelEntry<Refund> */
    public ModelEntry $refund;

    /** @var ModelEntry<Product> */
    public ModelEntry $product;

    /** @var ModelEntry<ProductImage> */
    public ModelEntry $productImage;

    /** @var ModelEntry<ProductOption> */
    public ModelEntry $productOption;

    /** @var ModelEntry<ProductOptionValue> */
    public ModelEntry $productOptionValue;

    /** @var ModelEntry<Setting> */
    public ModelEntry $setting;

    public function __construct(Repository $config)
    {
        $this->cart = new ModelEntry($config, 'larasell.models.cart', Cart::class);
        $this->cartItem = new ModelEntry($config, 'larasell.models.cart_item', CartItem::class);
        $this->category = new ModelEntry($config, 'larasell.models.category', Category::class);
        $this->inventoryReservation = new ModelEntry($config, 'larasell.models.inventory_reservation', InventoryReservation::class);
        $this->order = new ModelEntry($config, 'larasell.models.order', Order::class);
        $this->orderItem = new ModelEntry($config, 'larasell.models.order_item', OrderItem::class);
        $this->payment = new ModelEntry($config, 'larasell.models.payment', Payment::class);
        $this->refund = new ModelEntry($config, 'larasell.models.refund', Refund::class);
        $this->product = new ModelEntry($config, 'larasell.models.product', Product::class);
        $this->productImage = new ModelEntry($config, 'larasell.models.product_image', ProductImage::class);
        $this->productOption = new ModelEntry($config, 'larasell.models.product_option', ProductOption::class);
        $this->productOptionValue = new ModelEntry($config, 'larasell.models.product_option_value', ProductOptionValue::class);
        $this->setting = new ModelEntry($config, 'larasell.models.setting', Setting::class);
    }
}

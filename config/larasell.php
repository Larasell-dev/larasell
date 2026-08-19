<?php

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Models\ProductOption;
use Larasell\Larasell\Models\ProductOptionValue;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Payments\FakePaymentProvider;

return [
    'models' => [
        'cart' => Cart::class,
        'cart_item' => CartItem::class,
        'category' => Category::class,
        'order' => Order::class,
        'order_item' => OrderItem::class,
        'payment' => Payment::class,
        'product' => Product::class,
        'product_image' => ProductImage::class,
        'product_option' => ProductOption::class,
        'product_option_value' => ProductOptionValue::class,
    ],

    'images' => [
        'disk' => env('LARASELL_IMAGES_DISK', config('filesystems.default')),
        'path' => env('LARASELL_IMAGES_PATH', 'larasell/products'),
        'visibility' => env('LARASELL_IMAGES_VISIBILITY', 'public'),
    ],

    'order_numbers' => [
        'generator' => SequentialOrderNumberGenerator::class,
        'prefix' => env('LARASELL_ORDER_NUMBER_PREFIX', 'LS-'),
        'padding' => 6,
    ],

    'payments' => [
        'provider' => FakePaymentProvider::class,
        'fake' => [
            'succeeds' => true,
        ],
    ],
];

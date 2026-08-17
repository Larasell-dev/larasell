<?php

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Models\ProductOption;
use Larasell\Larasell\Models\ProductOptionValue;

return [
    'models' => [
        'cart' => Cart::class,
        'cart_item' => CartItem::class,
        'category' => Category::class,
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
];

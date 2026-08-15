<?php

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Product;

return [
    'models' => [
        'cart' => Cart::class,
        'cart_item' => CartItem::class,
        'category' => Category::class,
        'product' => Product::class,
    ],
];

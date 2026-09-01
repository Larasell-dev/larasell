<?php

use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxRoundingMode;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\InventoryReservation;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductAttributeValue;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Models\PromotionRedemption;
use Larasell\Larasell\Models\Refund;
use Larasell\Larasell\Models\Setting;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Payments\OfflinePaymentProvider;
use Larasell\Larasell\Promotions\DefaultPromotionCustomerResolver;
use Larasell\Larasell\Taxes\ConfigTaxRateResolver;
use Larasell\Larasell\Taxes\DestinationTaxJurisdictionResolver;
use Larasell\Larasell\Taxes\NoTaxCalculator;

return [
    'models' => [
        'cart' => Cart::class,
        'cart_item' => CartItem::class,
        'category' => Category::class,
        'inventory_reservation' => InventoryReservation::class,
        'order' => Order::class,
        'order_item' => OrderItem::class,
        'payment' => Payment::class,
        'refund' => Refund::class,
        'product' => Product::class,
        'product_image' => ProductImage::class,
        'product_attribute' => ProductAttribute::class,
        'product_attribute_value' => ProductAttributeValue::class,
        'product_variant' => ProductVariant::class,
        'promotion_redemption' => PromotionRedemption::class,
        'setting' => Setting::class,
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

    'promotions' => [
        'customer_resolver' => DefaultPromotionCustomerResolver::class,
    ],

    'taxes' => [
        'calculator' => NoTaxCalculator::class,
        'price_mode' => TaxPriceMode::Exclusive->value,
        'rounding' => TaxRoundingMode::HalfUp->value,
        'rounding_level' => 'line',
        'jurisdiction_resolver' => DestinationTaxJurisdictionResolver::class,
        'rate_resolver' => ConfigTaxRateResolver::class,
        'shipping_category' => 'shipping',
        'rates' => [
            'DE' => [
                'standard' => [
                    'identifier' => 'de-vat-standard',
                    'name' => 'German VAT',
                    'rate' => '19.0000',
                ],
                'reduced' => [
                    'identifier' => 'de-vat-reduced',
                    'name' => 'German reduced VAT',
                    'rate' => '7.0000',
                ],
                'zero' => [
                    'identifier' => 'de-vat-zero',
                    'name' => 'German zero-rated VAT',
                    'rate' => '0.0000',
                    'treatment' => 'zero_rated',
                ],
                'exempt' => [
                    'identifier' => 'de-vat-exempt',
                    'name' => 'German VAT exempt',
                    'rate' => '0.0000',
                    'treatment' => 'exempt',
                ],
                'shipping' => [
                    'identifier' => 'de-vat-shipping',
                    'name' => 'German shipping VAT',
                    'rate' => '19.0000',
                ],
            ],
        ],
    ],

    'payments' => [
        'default' => 'cash',
        'methods' => [
            'cash' => [
                'driver' => 'offline',
                'provider' => OfflinePaymentProvider::class,
                'inventory_reservation_minutes' => 1440,
            ],
            'bank_transfer' => [
                'driver' => 'offline',
                'provider' => OfflinePaymentProvider::class,
                'inventory_reservation_minutes' => 4320,
            ],
        ],
    ],
];

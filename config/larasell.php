<?php

use Larasell\Larasell\Carts\Strategies\CombineQuantities;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxRoundingMode;
use Larasell\Larasell\Media\DiskMediaStorage;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Payments\OfflinePaymentProvider;
use Larasell\Larasell\Promotions\DefaultPromotionCustomerResolver;
use Larasell\Larasell\Taxes\ConfigTaxRateResolver;
use Larasell\Larasell\Taxes\DestinationTaxJurisdictionResolver;
use Larasell\Larasell\Taxes\NoTaxCalculator;

return [
    'carts' => [
        'merge_strategy' => CombineQuantities::class,
    ],

    'media' => [
        // The MediaStorage that keeps uploaded files; the default writes them to the disks below.
        'storage' => DiskMediaStorage::class,
        // Public files go to a disk the browser can reach; private ones to one it can't.
        'disks' => [
            'public' => env('LARASELL_MEDIA_PUBLIC_DISK', 'public'),
            'private' => env('LARASELL_MEDIA_PRIVATE_DISK', 'local'),
        ],
        'path' => env('LARASELL_MEDIA_PATH', 'larasell/media'),
        'thumbnails' => [
            // The longer side, in pixels.
            'size' => 400,
            // Workers on other servers need disks they share with the app, e.g. S3.
            'queue' => env('LARASELL_MEDIA_QUEUE', 'media'),
        ],
        // Takes the frame a video's thumbnail is made of; needed where the queue worker runs.
        'ffmpeg' => env('LARASELL_FFMPEG', 'ffmpeg'),
    ],

    'licensing' => [
        'driver' => env('LARASELL_LICENSE_DRIVER', 'env'),
        'plan' => env('LARASELL_PLAN', 'pro'),
    ],

    'order_numbers' => [
        'generator' => SequentialOrderNumberGenerator::class,
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

<?php

use Larasell\Larasell\Carts\Strategies\CombineQuantities;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxRoundingMode;
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
        // The provider new files go to, unless their type has its own below. Each asset remembers
        // its provider, so changing these leaves earlier files where they are.
        'provider' => env('LARASELL_MEDIA_PROVIDER', 'local'),
        'provider_for' => [
            'image' => env('LARASELL_MEDIA_IMAGE_PROVIDER'),
            'video' => env('LARASELL_MEDIA_VIDEO_PROVIDER'),
        ],
        'providers' => [
            // On the disks below.
            'local' => ['driver' => 'local'],
            's3' => [
                'driver' => 's3',
                'disks' => [
                    'public' => env('LARASELL_MEDIA_S3_PUBLIC_DISK', 's3'),
                    'private' => env('LARASELL_MEDIA_S3_PRIVATE_DISK', 's3'),
                ],
            ],
            // Videos only. Mux tells the app when a video is ready with a webhook, which the app
            // passes to HandleMuxWebhook once MuxWebhookSignature accepted it.
            'mux' => [
                'driver' => 'mux',
                'token_id' => env('MUX_TOKEN_ID'),
                'token_secret' => env('MUX_TOKEN_SECRET'),
                'webhook_secret' => env('MUX_WEBHOOK_SECRET'),
                // For private videos, which play by signed URLs.
                'signing_key_id' => env('MUX_SIGNING_KEY_ID'),
                'signing_key' => env('MUX_SIGNING_KEY'),
            ],
        ],
        // The local provider's disks: public files go to one the browser can reach; private ones to one it can't.
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

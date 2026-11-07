<?php

/*
 * L8: dependencies between modules point one way. A module may use the modules in earlier tiers,
 * never those in later ones. SEO and the workflows are leaves: nothing else may use them.
 * Classes that break a rule today are listed in BASELINE, so new violations fail; remove an entry
 * once its class follows the rule.
 */

const MODULE_TIERS = [
    ['SalesChannels' => ['Larasell\Larasell\SalesChannels']],
    ['Storefront' => ['Larasell\Larasell\Storefront']],
    ['Catalog' => ['Larasell\Larasell\Catalog']],
    [
        'Inventory' => ['Larasell\Larasell\Inventory', 'Larasell\Larasell\Models\InventoryReservation'],
        'Taxes' => ['Larasell\Larasell\Taxes'],
        'Shipping' => ['Larasell\Larasell\Shipping'],
        'Promotions' => ['Larasell\Larasell\Promotions', 'Larasell\Larasell\Discounts', 'Larasell\Larasell\Models\PromotionRedemption'],
        'Customers' => ['Larasell\Larasell\Customers', 'Larasell\Larasell\Models\Customer'],
    ],
    ['Carts' => ['Larasell\Larasell\Carts', 'Larasell\Larasell\Models\Cart']],
    ['Checkout' => ['Larasell\Larasell\Checkout', 'Larasell\Larasell\OrderNumbers', 'Larasell\Larasell\Models\Order']],
    [
        'Payments' => ['Larasell\Larasell\Payments', 'Larasell\Larasell\Models\Payment'],
        'Refunds' => ['Larasell\Larasell\Refunds', 'Larasell\Larasell\Models\Refund'],
    ],
];

const LEAF_MODULES = [
    'Seo' => ['Larasell\Larasell\Seo'],
    'Workflows' => ['Larasell\Larasell\Workflows'],
];

/** What SEO may use besides the shared kernel. */
const SEO_DEPENDS_ON = ['SalesChannels', 'Storefront', 'Catalog'];

/** Today's violations: module => forbidden module => classes of the module that use it. */
const BASELINE = [
    'Inventory' => [
        'Checkout' => [
            'Larasell\Larasell\Inventory\Commands\ReleaseExpiredInventoryCommand',
            'Larasell\Larasell\Inventory\ReleaseExpiredInventoryForOrder',
            'Larasell\Larasell\Models\InventoryReservation',
        ],
    ],
    'Taxes' => [
        'Carts' => [
            'Larasell\Larasell\Taxes\CartTaxEstimator',
        ],
    ],
    'Shipping' => [
        'Carts' => [
            'Larasell\Larasell\Shipping\ShippingManager',
            'Larasell\Larasell\Shipping\ShippingMethod',
        ],
    ],
    'Promotions' => [
        'Checkout' => [
            'Larasell\Larasell\Promotions\ReleaseExpiredPromotionRedemptionsForOrder',
            'Larasell\Larasell\Promotions\Commands\ReleaseExpiredPromotionRedemptionsCommand',
            'Larasell\Larasell\Models\PromotionRedemption',
        ],
        'Carts' => [
            'Larasell\Larasell\Discounts\PromotionContext',
            'Larasell\Larasell\Discounts\DiscountResult',
            'Larasell\Larasell\Discounts\PromotionManager',
        ],
    ],
    'Customers' => [
        'Carts' => [
            'Larasell\Larasell\Customers\StorefrontUser',
        ],
        'Checkout' => [
            'Larasell\Larasell\Models\Customer',
        ],
    ],
    'Checkout' => [
        'Payments' => [
            'Larasell\Larasell\Checkout\CheckoutResult',
            'Larasell\Larasell\Checkout\Checkout',
            'Larasell\Larasell\Models\Order',
        ],
    ],
];

/** @return array<string, list<string>> */
function moduleNamespaces(): array
{
    return array_merge(...MODULE_TIERS, ...[LEAF_MODULES]);
}

/** @return array<string, array{list<string>, list<string>}> Module => [its namespaces, namespaces it must not use] */
function forbiddenDependencies(): array
{
    $all = moduleNamespaces();
    $forbidden = [];

    foreach (MODULE_TIERS as $tier => $modules) {
        $later = array_merge(...array_slice(MODULE_TIERS, $tier + 1), ...[LEAF_MODULES]);

        foreach ($modules as $module => $namespaces) {
            $forbidden[$module] = [$namespaces, $later];
        }
    }

    $forbidden['Seo'] = [LEAF_MODULES['Seo'], array_diff_key($all, array_flip([...SEO_DEPENDS_ON, 'Seo']))];
    $forbidden['Workflows'] = [LEAF_MODULES['Workflows'], []];

    return $forbidden;
}

// One expectation per namespace: with several targets, the arch plugin only checks the first one.
foreach (forbiddenDependencies() as $module => [$namespaces, $others]) {
    foreach ($namespaces as $namespace) {
        foreach ($others as $other => $otherNamespaces) {
            arch("{$namespace} ({$module}) does not use {$other}")
                ->expect($namespace)
                ->not->toUse($otherNamespaces)
                ->ignoring(BASELINE[$module][$other] ?? []);
        }
    }
}

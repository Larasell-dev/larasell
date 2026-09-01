---
title: Taxes
description: Configure tax rates, classify products, estimate cart tax, and read order tax snapshots.
---

# Taxes

Larasell separates tax calculation from carts and checkout through the
`TaxCalculator` contract. The package includes:

- `NoTaxCalculator`, the default, for applications that intentionally do not
  calculate tax.
- `ConfiguredTaxCalculator` for country and state rates stored in
  `config/larasell.php`.
- Contracts for replacing jurisdiction and rate resolution or the complete
  calculation through an external provider.

Tax amounts and prices use integer minor units. Rates use decimal strings such
as `'19.0000'`; do not configure rates as floating-point numbers.

## Enabling configured taxes

Publish the package configuration and select the configured calculator:

```bash
php artisan vendor:publish --tag=larasell-config
```

```php
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Taxes\ConfiguredTaxCalculator;

'taxes' => [
    'calculator' => ConfiguredTaxCalculator::class,
    'price_mode' => TaxPriceMode::Inclusive->value,
    // ...
],
```

The default remains `NoTaxCalculator`. This is an explicit no-tax result, not
an estimate caused by missing customer information. Choose a tax calculator
before accepting production orders.

One cart uses one price mode:

- `inclusive`: catalog prices already contain tax. Calculating tax extracts the
  tax portion without increasing the customer-facing total.
- `exclusive`: catalog prices do not contain tax. Calculated tax is added to
  the checkout total.

The configured value is a string because Laravel configuration must remain
cacheable. Larasell converts it to `TaxPriceMode` at the domain boundary.

## Configuring rates

Configured rates are grouped by jurisdiction and tax category:

```php
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
    ],
],
```

Jurisdiction keys use an uppercase country code, optionally followed by a
state or region code. For example, `US-CA` is checked before the `US` fallback.
Each configured rule needs a stable identifier, snapshot name, and decimal
rate string.

Supported treatments are:

- `taxable`: a positive rate applies.
- `zero_rated`: the sale remains part of the taxable base at a zero rate.
- `exempt`: the line has no taxable base or tax amount.
- `not_taxable`: used by the explicit no-tax calculator.

Changing configuration only affects later calculations. Completed orders keep
their original rate, treatment, jurisdiction, amounts, and labels in tax
snapshots.

## Product categories

Products default to the `standard` category. Set another category when the
product is created or updated:

```php
$book->update(['tax_category' => 'reduced']);
$insurance->update(['tax_category' => 'exempt']);
```

A variant inherits its product's category unless it defines an override:

```php
$variant->update(['tax_category' => 'reduced']);

$variant->effectiveTaxCategory();
```

Categories describe what is sold. They do not contain rates. The active rate
resolver maps the category and resolved jurisdiction to a tax rule.

## Shipping tax

Shipping is a separate taxable line and is rounded separately from products.
Its category defaults to `shipping`:

```php
'shipping_category' => 'shipping',

'rates' => [
    'DE' => [
        'shipping' => [
            'identifier' => 'de-vat-shipping',
            'name' => 'German shipping VAT',
            'rate' => '19.0000',
        ],
    ],
],
```

The built-in configuration applies one shipping category to the selected
shipping option. When shipping tax depends on the products in a mixed-rate
shipment, provide a custom rate resolver or calculator for that policy.

## Cart estimates

Pass the location information currently available in the storefront:

```php
use Larasell\Larasell\Taxes\CartTaxEstimateRequest;

$estimate = $cart->taxEstimate(new CartTaxEstimateRequest(
    shippingAddress: $shippingAddress,
    billingAddress: $billingAddress,
    customerIdentifier: (string) $customer?->getKey(),
));

$estimate->subtotal;
$estimate->shippingAmount;
$estimate->discountAmount;
$estimate->amountBeforeTax();
$estimate->tax->taxAmount();
$estimate->total();
```

The tax result status communicates how reliable the estimate is:

- `calculated`: the calculator had authoritative location information.
- `provisional`: a fallback, normally the billing address, was used.
- `unavailable`: required location or rate information was missing.

With exclusive prices, `total()` returns `null` when tax is unavailable because
the payable total is not yet known. With inclusive prices, the displayed total
remains known even if its tax breakdown is unavailable. Empty cart totals are
also `null`.

The built-in destination resolver uses the shipping address first. When no
shipping address is present, it uses the billing address provisionally. A cart
estimate must not be treated as the final amount charged to the customer.

## Checkout behavior

Checkout recalculates tax inside the order transaction using the locked cart,
current product classifications, selected shipping option, final addresses,
and the same discount allocations used for the order.

Checkout only accepts a `calculated` result. A provisional or unavailable
result throws `TaxCalculationException`. The transaction creates no order or
payment, does not decrement inventory, and does not clear the cart.

```php
use Larasell\Larasell\Taxes\Exceptions\TaxCalculationException;

try {
    $result = $checkout->create($cart, $data);
} catch (TaxCalculationException $exception) {
    return back()->withErrors(['address' => $exception->getMessage()]);
}
```

Do not convert this exception to zero tax. Ask for the missing information or
correct the tax configuration before retrying checkout.

## Order snapshots

Successful checkout stores the authoritative result independently of future
configuration:

```php
$order->tax_price_mode;          // TaxPriceMode
$order->tax_total;               // Price
$order->tax_snapshot;            // Aggregate snapshot
$order->shipping_tax_total;      // Price|null
$order->shipping_tax_snapshot;   // array|null

foreach ($order->items as $item) {
    $item->tax_category;
    $item->taxable_amount;
    $item->tax_total;
    $item->tax_snapshot;
}
```

Snapshots contain a version, price mode, category, treatment, gross and
discounted amounts, taxable amount, tax amount, components, jurisdiction, and
calculator metadata where applicable. Use the snapshots for invoices,
reporting, and refunds instead of recalculating historical orders with current
configuration.

## Replacing tax resolution

Use a custom `TaxJurisdictionResolver` when the destination-based default does
not match the transaction, for example digital goods, store pickup, or an
origin-based jurisdiction. Use a custom `TaxRateResolver` when rates come from
a database or another internal rule source.

```php
use App\Taxes\DatabaseTaxRateResolver;
use App\Taxes\DigitalGoodsJurisdictionResolver;

'taxes' => [
    'jurisdiction_resolver' => DigitalGoodsJurisdictionResolver::class,
    'rate_resolver' => DatabaseTaxRateResolver::class,
],
```

Implement these contracts:

```php
use Larasell\Larasell\Contracts\TaxJurisdictionResolver;
use Larasell\Larasell\Contracts\TaxRateResolver;
use Larasell\Larasell\Taxes\TaxCalculationContext;
use Larasell\Larasell\Taxes\TaxJurisdictionResolution;

final class DigitalGoodsJurisdictionResolver implements TaxJurisdictionResolver
{
    public function resolve(TaxCalculationContext $context): TaxJurisdictionResolution
    {
        // Return calculated(), provisional(), or unavailable().
    }
}
```

Replace the complete `TaxCalculator` when an external service must determine
jurisdictions, rates, and amounts. A calculator receives a
`TaxCalculationContext` and must return one `TaxLineResult` for every input
line, including shipping. Provider amounts are authoritative; preserve their
component identifiers and metadata in the result.

```php
use App\Taxes\ProviderTaxCalculator;

'taxes' => [
    'calculator' => ProviderTaxCalculator::class,
],
```

Provider failures should throw `TaxCalculationException`. Do not silently fall
back to `NoTaxCalculator` or a zero amount during checkout.

## Current scope

The built-in calculator is intended for straightforward configured rates. It
does not provide tax registration or nexus decisions, address validation,
customer exemption certificate management, EU VAT ID validation, reverse
charge rules, invoice generation, tax returns, or provider filing.

Use an external calculator when legal rules require more than the configured
country/state and category mapping. Have the final classification, rate,
shipping, invoicing, and reporting setup reviewed for the jurisdictions where
the business is registered or required to collect tax.

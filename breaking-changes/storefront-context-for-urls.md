# Absolute URLs need a `StorefrontContext`

A sales channel no longer has to be a web shop: POS, headless or marketplace
channels have no URL. So `SalesContext` now holds only channel, locale and
currency, and everything that builds an absolute URL takes a
`Larasell\Larasell\Storefront\StorefrontContext`: a sales context plus the
entry point (host and prefix) it is reached through. Entry points, storefront
routing and `storefront_route()` moved to the `Larasell\Larasell\Storefront`
namespace; its service provider is auto-discovered.

| Before | After |
| --- | --- |
| `SalesContext::for('default', 'en')->url($path)` | `StorefrontContext::for('default', 'en')->url($path)` |
| `$context->entryPoint` on `SalesContext` | `$storefront->entryPoint`, `$storefront->sales` for the rest |
| `storefront_route($name, $params, SalesContext $context)` | `storefront_route($name, $params, StorefrontContext $context)` |
| `Seo::url($page, SalesContext $context)`, `availableUrl`, `preload`, `resolve` | the same with a `StorefrontContext` |
| `GoneStrategy`, `HiddenPagePolicy`, `SeoPageGone::$context`, `SeoPageHidden::$context` | typed with `StorefrontContext` |
| `Larasell\Larasell\SalesChannels\Actions\AddEntryPoint`, `DeleteEntryPoint` | `Larasell\Larasell\Storefront\Actions\…` |
| `Larasell\Larasell\SalesChannels\Events\EntryPointCreated`, `EntryPointDeleted` | `Larasell\Larasell\Storefront\Events\…` |
| `SalesChannelType` enum | a string type registered with `SalesChannelTypes`, e.g. `'storefront'` |
| `CreateSalesChannel` with `entry_points` | `CreateSalesChannel` with `type`, then `AddEntryPoint` per entry point |
| table `larasell_sales_channel_entry_points`, column `entry_point_id` on carts and orders | table `larasell_storefront_entry_points`; carts and orders store no entry point |

## Who is affected

Apps that build URLs with a context they pass themselves, implement a gone
strategy or hidden page policy, or create channels and entry points in
seeders. Storefront controllers that rely on the current request's context
keep working.

## How to upgrade

```php
// Before
use Larasell\Larasell\SalesChannels\SalesContext;

$url = Seo::url($product, SalesContext::for('default', 'de'));
$link = storefront_route('order', ['order' => $order->public_id], $order->sales_context);

// After
use Larasell\Larasell\Storefront\StorefrontContext;

$url = Seo::url($product, StorefrontContext::for('default', 'de'));
// An order links back through the primary entry point of its channel, locale and currency.
$link = storefront_route('order', ['order' => $order->public_id], StorefrontContext::for($order->sales_context));
```

To sell through another kind of channel, register its type, e.g. in a service
provider:

```php
$this->callAfterResolving(SalesChannelTypes::class, fn (SalesChannelTypes $types) => $types->register('pos'));
```

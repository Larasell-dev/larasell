---
title: Order Numbers
description: Generate customizable, concurrency-safe order numbers.
---

# Order Numbers

Use `OrderNumberFactory` when creating an order. It allocates the sequence in
the database, so concurrent requests cannot receive the same sequence.

```php
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;

$number = app(OrderNumberFactory::class)->generate(); // LS-000001
```

The sequence table intentionally keeps one small allocation row per number.
Do not calculate the next number from the latest order, because concurrent
checkouts could calculate the same value.

Change the default prefix with `LARASELL_ORDER_NUMBER_PREFIX`, or publish the
configuration to change its padding:

```php
'order_numbers' => [
    'prefix' => 'ORDER-',
    'padding' => 8,
],
```

## Custom formats

Implement `OrderNumberGenerator` when the format needs more than a prefix and
padding, then configure its class under `larasell.order_numbers.generator`.

```php
namespace App\OrderNumbers;

use Larasell\Larasell\Contracts\OrderNumberGenerator;

class StoreOrderNumberGenerator implements OrderNumberGenerator
{
    public function generate(int $sequence): string
    {
        return sprintf('WEB-%08d', $sequence);
    }
}
```

Publish Larasell's configuration if the application does not already have a
`config/larasell.php` file:

```bash
php artisan vendor:publish --tag=larasell-config
```

Then import the custom generator and replace the `generator` entry in
`config/larasell.php`:

```php
use App\OrderNumbers\StoreOrderNumberGenerator;

return [
    // ...

    'order_numbers' => [
        'generator' => StoreOrderNumberGenerator::class,
        'prefix' => env('LARASELL_ORDER_NUMBER_PREFIX', 'LS-'),
        'padding' => 6,
    ],
];
```

The `prefix` and `padding` settings are used only by the default
`SequentialOrderNumberGenerator`. A custom generator may define and read its
own configuration values.

Custom generators must preserve the uniqueness of the supplied sequence in
their result. The generated value should be stored in a uniquely indexed order
column when the order is created.

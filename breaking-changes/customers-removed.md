# Customers are removed

The `Customer` model, its tables and the order's `customer_id` are removed so a new customer
module can be built from scratch. Orders keep the name, email and phone they were placed with.

## Who is affected

Apps that use `Larasell\Larasell\Models\Customer` or `CustomerUser`, call `customer()` or
`createCustomer()` on a storefront user, pass `customer_id` to `Checkout::create()`, read
`Order::$customer_id` or `$order->customer`, or implement `PromotionCustomerResolver`.

A migration drops the `larasell_customers` and `larasell_customer_user` tables and the
`customer_id` column of `larasell_orders`, with their data. Run `php artisan migrate`.

## How to upgrade

The `StorefrontUser` trait moved to the carts and only adds `carts()` now:

```php
// Before
use Larasell\Larasell\Customers\StorefrontUser;

// After
use Larasell\Larasell\Carts\StorefrontUser;
```

Stop passing `customer_id` to the checkout:

```php
// Before
app(Checkout::class)->create($cart, ['customer_email' => $email, 'customer_name' => $name, 'customer_id' => $id]);

// After
app(Checkout::class)->create($cart, ['customer_email' => $email, 'customer_name' => $name]);
```

Per-customer promotion limits now always count by email. A custom `PromotionCustomerResolver`
gets only the email:

```php
// Before
public function resolve(?int $customerId, string $email): string

// After
public function resolve(string $email): string
```

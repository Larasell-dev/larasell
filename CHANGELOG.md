# Changelog

## [1.28.0](https://github.com/Larasell-dev/larasell/compare/v1.27.4...v1.28.0) (2026-11-11)


### ⚠ BREAKING CHANGES

* move the audit log into the core and audit changes with an Origin ([#182](https://github.com/Larasell-dev/larasell/issues/182))

### Features

* move the audit log into the core and audit changes with an Origin ([#182](https://github.com/Larasell-dev/larasell/issues/182)) ([741d53c](https://github.com/Larasell-dev/larasell/commit/741d53c6a3364c8981f9cef3e9ed923c4f2c2a4e))

## [1.27.4](https://github.com/Larasell-dev/larasell/compare/v1.27.3...v1.27.4) (2026-11-11)


### Features

* add ProductTypes and ProductCapabilities facades ([#180](https://github.com/Larasell-dev/larasell/issues/180)) ([63f27f1](https://github.com/Larasell-dev/larasell/commit/63f27f1057c15c3335a8116cba8dad3d2d85e8eb))

## [1.27.3](https://github.com/Larasell-dev/larasell/compare/v1.27.2...v1.27.3) (2026-11-10)


### Features

* add product types with capabilities ([#178](https://github.com/Larasell-dev/larasell/issues/178)) ([00d5d0e](https://github.com/Larasell-dev/larasell/commit/00d5d0e41bd5f66975a3d5ebadbad6fa3be3e273))

## [1.27.2](https://github.com/Larasell-dev/larasell/compare/v1.27.1...v1.27.2) (2026-11-10)


### Features

* store product descriptions as rich text ([#176](https://github.com/Larasell-dev/larasell/issues/176)) ([de75af2](https://github.com/Larasell-dev/larasell/commit/de75af24ebea80f4ca448aa2f804f85833dc10c9))

## [1.27.1](https://github.com/Larasell-dev/larasell/compare/v1.27.0...v1.27.1) (2026-11-10)


### Features

* add a customers module ([5b509cc](https://github.com/Larasell-dev/larasell/commit/5b509ccc704ab7cea8db1178d71a16a01cae0dc9))

## [1.27.0](https://github.com/Larasell-dev/larasell/compare/v1.26.0...v1.27.0) (2026-11-09)


### ⚠ BREAKING CHANGES

* remove customers

### Code Refactoring

* remove customers ([0d3f688](https://github.com/Larasell-dev/larasell/commit/0d3f68848add327b7d81cf00dde53070ceebe9ec))

### Upgrade guide

#### Customers are removed

The `Customer` model, its tables and the order's `customer_id` are removed so a new customer
module can be built from scratch. Orders keep the name, email and phone they were placed with.

##### Who is affected

Apps that use `Larasell\Larasell\Models\Customer` or `CustomerUser`, call `customer()` or
`createCustomer()` on a storefront user, pass `customer_id` to `Checkout::create()`, read
`Order::$customer_id` or `$order->customer`, or implement `PromotionCustomerResolver`.

A migration drops the `larasell_customers` and `larasell_customer_user` tables and the
`customer_id` column of `larasell_orders`, with their data. Run `php artisan migrate`.

##### How to upgrade

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

## [1.26.0](https://github.com/Larasell-dev/larasell/compare/v1.25.0...v1.26.0) (2026-11-07)


### ⚠ BREAKING CHANGES

* remove product images from the core

### Code Refactoring

* remove product images from the core ([e42507f](https://github.com/Larasell-dev/larasell/commit/e42507f7614bf0828903f324ac78bb75da00608e))

### Upgrade guide

#### Product images are gone from the core

The core no longer stores product images. They will come back in a dedicated media module,
so the old `ProductImage` model and its placeholder system are removed instead of kept alongside.

##### Who is affected

Apps that use any of these: `ProductImage`, `Product::images()`, `Product::$thumbnail`,
`ProductVariant::$thumbnail`, `CreateProductImage`, `UpdateProductImage`, `DeleteProductImage`,
`SetProductImages`, the `ProductImageCreated`/`ProductImageUpdated`/`ProductImageDeleted` events,
`ImageId`, `ProductImageFileCast`, `PlaceholderCast`, `PlaceholderGenerator` and everything in
`Larasell\Larasell\Images`, the `larasell:refresh-placeholders` command, or the `larasell.images`
config. `ProductUpdated::$changed` no longer contains `"images"`.

A new migration drops the `larasell_product_images` and `larasell_product_product_image` tables.
**Their rows are deleted**; the files on the images disk are left in place.

##### How to upgrade

Back up the two tables first if you need the data, remove any code using the classes above,
remove `images` from a published `config/larasell.php`, and run the migrations:

```sh
php artisan migrate
```

## [1.25.0](https://github.com/Larasell-dev/larasell/compare/v1.24.1...v1.25.0) (2026-11-07)


### ⚠ BREAKING CHANGES

* give custom URLs per channel and name the channel of a rejected one
* save custom URLs from attachments on the catalog payloads
* take payloads in the category actions
* move module exceptions to Exceptions namespaces as structured errors
* claim order numbers from one counter row per sequence
* check the sales channel key in CreateSalesChannel

### Features

* check the sales channel key in CreateSalesChannel ([e1a85c8](https://github.com/Larasell-dev/larasell/commit/e1a85c8d62ef12b27ecbe08694a1b243f10cf085))
* give custom URLs per channel and name the channel of a rejected one ([85416e3](https://github.com/Larasell-dev/larasell/commit/85416e34a3b5f460ae34de25625f9034378bbc69))
* save custom URLs from attachments on the catalog payloads ([5ba98ce](https://github.com/Larasell-dev/larasell/commit/5ba98cefa7d74aa5d762881f6ec7b616754c558b))
* take payloads in the category actions ([7fc571a](https://github.com/Larasell-dev/larasell/commit/7fc571aa2aa8b7ff04d6fa9a6ae0e9a00bd489f5))


### Bug Fixes

* claim order numbers from one counter row per sequence ([be853c3](https://github.com/Larasell-dev/larasell/commit/be853c3d419ee7d288808da82c7f5f1a8dc782f9))


### Code Refactoring

* move module exceptions to Exceptions namespaces as structured errors ([b3eb8cc](https://github.com/Larasell-dev/larasell/commit/b3eb8cc032446a892fd7a5e79a760e7b8994b78e))

### Upgrade guide

#### Category actions take payloads instead of attribute arrays

`CreateCategory`, `UpdateCategory` and `MoveCategory` now take typed payloads, so callers can't
pass unknown or misspelled attributes. The payloads can carry `Attachments`, which the actions hand
to `CategoryCreated` and `CategoryUpdated` for an app's own listeners. `MoveCategory` now also
numbers the new siblings from 0 and puts the category at the given position.

##### Who is affected

Apps that call `CreateCategory`, `UpdateCategory`, `MoveCategory`, `CreateCategoryWithUrls` or
`UpdateCategoryWithUrls` directly. Apps that rely on `CategoryUpdated` only firing when an
attribute changed: it now also fires, with `$changed === []`, when attachments were passed.

##### How to upgrade

```php
// Before
app(CreateCategory::class)->handle(['name' => ['en' => 'Shoes'], 'parent_id' => $parent->id, 'status' => Visibility::Visible]);
app(UpdateCategory::class)->handle(CategoryId::of($shoes), ['name' => ['en' => 'Footwear']]);
app(MoveCategory::class)->handle(CategoryId::of($boots), CategoryId::of($sale), 2);

// After
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
use Larasell\Larasell\Catalog\Actions\MoveCategoryPayload;
use Larasell\Larasell\Catalog\Actions\UpdateCategoryPayload;
use Larasell\Larasell\Translatable;

app(CreateCategory::class)->handle(new CreateCategoryPayload(
    name: new Translatable(['en' => 'Shoes']),
    status: Visibility::Visible,
    parentId: CategoryId::of($parent),
));
app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(name: new Translatable(['en' => 'Footwear'])));
app(MoveCategory::class)->handle(CategoryId::of($boots), new MoveCategoryPayload(CategoryId::of($sale), 2));
```

`CreateCategoryWithUrls::handle()` and `UpdateCategoryWithUrls::handle()` take the same payloads in
place of their `$attributes` array.

#### Custom URLs are attachments on the catalog payloads, and the `*WithUrls` workflows are gone

`CreateCategoryWithUrls`, `UpdateCategoryWithUrls`, `CreateProductWithUrls` and
`UpdateProductWithUrls` have been removed. Instead, pass a `Larasell\Larasell\Seo\Catalog\CustomUrls`
attachment to the catalog action. The SEO module listens to the catalog events and saves the URLs
inside the action's transaction, so a taken path still undoes the catalog write. Apps no longer need
a second entry point for every catalog action.

`CreateProduct` and `UpdateProduct` now take `CreateProductPayload` and `UpdateProductPayload`
instead of an attributes array. `ProductCreated` and `ProductUpdated` carry the payload's
`Attachments`. `ProductUpdated` now also fires when only attachments were passed, with
`$changed === []`.

##### Who is affected

- Apps that call any of the four `*WithUrls` workflows.
- Apps that call `CreateProduct` or `UpdateProduct` directly.
- Apps with `ProductUpdated` listeners that assume at least one attribute changed.

`DiscontinueProduct` is unchanged. Apps that only listen to `ProductCreated` are not affected.

##### How to upgrade

```php
// Before
use Larasell\Larasell\Workflows\CreateCategoryWithUrls;
use Larasell\Larasell\Workflows\UpdateProductWithUrls;

app(CreateProduct::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
app(UpdateProduct::class)->handle(ProductId::of($boots), ['sku' => 'B-2']);
app(CreateCategoryWithUrls::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Shoes'])), ['en' => 'footwear']);
app(UpdateProductWithUrls::class)->handle(ProductId::of($boots), ['name' => ['en' => 'Leather Boots']], ['de' => 'winter-angebot', 'en' => null]);

// After
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Seo\Catalog\CustomUrls;

app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]));
app(UpdateProduct::class)->handle(ProductId::of($boots), new UpdateProductPayload(['sku' => 'B-2']));
app(CreateCategory::class)->handle(new CreateCategoryPayload(
    new Translatable(['en' => 'Shoes']),
    attachments: new Attachments(CustomUrls::byLocale(['en' => 'footwear'])),
));
app(UpdateProduct::class)->handle(ProductId::of($boots), new UpdateProductPayload(
    ['name' => ['en' => 'Leather Boots']],
    new Attachments(CustomUrls::byLocale(['de' => 'winter-angebot', 'en' => null])),
));
```

`CustomUrls::byLocale()` writes a path to every channel that serves its locale. To give channels
in the same locale their own paths, use `CustomUrls::byChannel()` with paths keyed by sales channel ID:

```php
CustomUrls::byChannel('en', [$shop->id => 'footwear', $uk->id => 'trainers', $outlet->id => null]);
```

Both throw an `InvalidArgumentException` for a locale or channel with no URLs, before anything is
written. A null path still goes back to the automatic URL.

The workflows threw `InvalidSeoPath` for a rejected path. The catalog actions now throw
`Larasell\Larasell\Seo\Exceptions\CustomUrlRejected` instead. It names the channel and locale
(`$context`) and carries the `$reason` and `$path`, with the original `InvalidSeoPath` as its
previous exception:

```php
try {
    app(UpdateCategory::class)->handle($id, $payload);
} catch (CustomUrlRejected $exception) {
    // e.g. show $exception->reason on the field of $exception->context->channelId
}
```

#### Module exceptions moved to `<Module>\Exceptions` and are Chronicle structured errors

Every module now keeps its exceptions in its own `Exceptions` namespace, and
each one implements Chronicle's `StructuredException`, so logs and error pages
get an error code, a why and a fix. Messages are unchanged.

##### Who is affected

Apps that import, catch or throw one of these classes:

| Before | After |
| --- | --- |
| `Larasell\Larasell\Ids\InvalidId` | `Larasell\Larasell\Ids\Exceptions\InvalidId` |
| `Larasell\Larasell\SalesChannels\InvalidSalesChannel` | `Larasell\Larasell\SalesChannels\Exceptions\InvalidSalesChannel` |
| `Larasell\Larasell\SalesChannels\SalesChannelInUse` | `Larasell\Larasell\SalesChannels\Exceptions\SalesChannelInUse` |
| `Larasell\Larasell\Seo\InvalidSeoPath` | `Larasell\Larasell\Seo\Exceptions\InvalidSeoPath` |

`InvalidSalesChannel` is now built through named constructors only, e.g.
`InvalidSalesChannel::unknown('b2b')`.

##### How to upgrade

```php
// Before
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;

throw new InvalidSalesChannel('There is no sales channel [b2b].');

// After
use Larasell\Larasell\SalesChannels\Exceptions\InvalidSalesChannel;

throw InvalidSalesChannel::unknown('b2b');
```

Update the other imports from the table the same way.

#### `larasell_order_number_sequences` keeps one counter row per sequence

The table used to get a new row for every order number issued. Concurrent
checkouts read the highest number, tried to insert the next one and retried on a
collision, which deadlocked on MySQL and MariaDB and failed outright on
PostgreSQL. Now each sequence is a single row holding the last number it
issued, and `SingleOrderNumberSequence` and `MultipleOrderNumberSequence` claim
the next number by incrementing that row inside the checkout's transaction.
Concurrent checkouts wait for each other on that row instead of colliding.

The PHP API is unchanged: `OrderNumberSequence::next()` still returns the next
number, honours `larasell.order_numbers.start` the same way, and order numbers
continue where they left off. A checkout that rolls back now gives its number
back, so numbers no longer skip after a failed checkout.

##### Who is affected

Apps that read or write `larasell_order_number_sequences` themselves, for
example to count issued numbers or to look up when a number was issued. Its
`id` and `created_at` columns are gone, and it holds one row per sequence name
with the last number in `number`. Apps that only use the sequences or order
numbers through Larasell are not affected; run the migration.

##### How to upgrade

Run the migration. It keeps the highest issued number of every sequence, so the
next order number follows the last one:

```sh
php artisan migrate
```

Read a sequence's last number from its row instead of from the newest row:

```php
// Before
DB::table('larasell_order_number_sequences')->where('name', 'default')->max('number');
DB::table('larasell_order_number_sequences')->where('name', 'default')->count();

// After
DB::table('larasell_order_number_sequences')->where('name', 'default')->value('number');
```

The issued numbers themselves are on the orders, in `larasell_orders.number`.

#### `CreateSalesChannel` checks the channel key

`CreateSalesChannel` now refuses a key that isn't lowercase letters and numbers joined by `-` or
`_` (e.g. `b2b-shop`), is longer than 64 characters, or another channel already has. It throws
`InvalidSalesChannel`, like for its other checks. Code names a channel by its key, as in
`SalesContext::for('b2b-shop')`, so the rule belongs with the channel rather than with each
form that creates one. A duplicate key used to fail with a database `QueryException`.

##### Who is affected

Apps that call `Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel` with a key in
capitals, with spaces or other characters, or that catch `QueryException` for a duplicate key.
Existing channels are not checked or changed; apps that already use keys like `default` or
`b2b-shop` can skip this entry.

##### How to upgrade

Pass a key in the new format, and catch `InvalidSalesChannel` for a duplicate:

```php
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;

// Before
try {
    app(CreateSalesChannel::class)->handle(['key' => 'B2B Shop', /* … */]);
} catch (\Illuminate\Database\QueryException) {
    // the key was taken
}

// After
try {
    app(CreateSalesChannel::class)->handle(['key' => 'b2b-shop', /* … */]);
} catch (InvalidSalesChannel $exception) {
    // the key is taken or malformed; $exception->getMessage() says which
}
```

## [1.24.1](https://github.com/Larasell-dev/larasell/compare/v1.24.0...v1.24.1) (2026-11-07)


### Bug Fixes

* cache storefront entry points as plain rows ([4178eae](https://github.com/Larasell-dev/larasell/commit/4178eae9d9383edd6edcb5228a2d83b644c8144a))
* tell translations apart that only look equal as numbers ([3c3022b](https://github.com/Larasell-dev/larasell/commit/3c3022b258cc894775ed0be3c8c4cecc36d5290e))

## [1.24.0](https://github.com/Larasell-dev/larasell/compare/v1.23.0...v1.24.0) (2026-11-07)


### ⚠ BREAKING CHANGES

* **storefront:** let entry points serve a locale, not a currency
* pass typed IDs across module boundaries
* add typed IDs and take them in cart add, set and remove
* create carts and orders in an explicit sales context
* split the storefront off sales channels
* add the sales channel module
* keep SEO URLs in step through catalog events, in the same transaction
* move catalog models to Larasell\Larasell\Catalog\Models
* remove the model registry
* replace product and category slugs with SEO URLs

### Features

* add the sales channel module ([6fc4ccf](https://github.com/Larasell-dev/larasell/commit/6fc4ccf82b2835f6e1bb4e76ea0d1b62c5a5fd0b))
* add the SEO module with sales channel entry points ([a79b716](https://github.com/Larasell-dev/larasell/commit/a79b7162ea549970f3390f6d1d63b34900cf8435))
* add workflows for discontinuing products and saving pages with custom URLs ([b31a735](https://github.com/Larasell-dev/larasell/commit/b31a73547c8dc975856cf32f8ce7c66cdfe34c02))
* generate links to SEO URLs ([014ac6a](https://github.com/Larasell-dev/larasell/commit/014ac6a1a69132aef2e6f8b11ae85e0c46978455))
* keep SEO URLs in step through catalog events, in the same transaction ([cb9fe9f](https://github.com/Larasell-dev/larasell/commit/cb9fe9f681dbd910ddd6df2de7f6b35f2e573836))
* keep SEO URLs with history for products and categories ([8d0d3b3](https://github.com/Larasell-dev/larasell/commit/8d0d3b369648fcc1fefc60ffbfdec836e70d5c85))
* replace product and category slugs with SEO URLs ([0886f2e](https://github.com/Larasell-dev/larasell/commit/0886f2eb15792b8bf04eba5ec8ebf8792990de45))
* **sales-channels:** add DeleteSalesChannel ([77d2e8f](https://github.com/Larasell-dev/larasell/commit/77d2e8feed28a33ffb7ecdbabb90d76f8b89a3c8))
* **seo:** say why a path is invalid ([40b0f5b](https://github.com/Larasell-dev/larasell/commit/40b0f5b0cc2bc4a9a9feb8e615aef1bfd597f922))
* serve storefront pages through SEO URLs ([0fe8a04](https://github.com/Larasell-dev/larasell/commit/0fe8a044141e0bf8c1f896b49afa75eac7dbadb4))
* **storefront:** let entry points serve a locale, not a currency ([1b943b4](https://github.com/Larasell-dev/larasell/commit/1b943b4669badba816de96312f4113e10b634cad))
* translate storefront route paths with -&gt;localized() ([c2e260e](https://github.com/Larasell-dev/larasell/commit/c2e260e1ed4cd9fb4beb0d4c0fc7e2aa6736fb80))
* write the catalog through actions that dispatch domain events ([88c7d7e](https://github.com/Larasell-dev/larasell/commit/88c7d7e5b04014e36b3d72bcbbdb9aa194b23c59))


### Bug Fixes

* announce SEO URL changes after commit and keep a replacement chosen after delete ([8c661e2](https://github.com/Larasell-dev/larasell/commit/8c661e2333ce1b12916a8847886474d4977a0ac6))
* **catalog:** compare translations by value, not by stored JSON ([124773d](https://github.com/Larasell-dev/larasell/commit/124773d0b582d81f54c3a2e98f42ddeec0828d27))
* check the Route::seo() controller map when the route is registered ([1e28717](https://github.com/Larasell-dev/larasell/commit/1e2871774310b0c82c3410b6a5a50b97f3cbfe25))
* keep the path of non-SEO storefront routes as sent ([34b2d8f](https://github.com/Larasell-dev/larasell/commit/34b2d8f2f09698af34caba3c7e7757c9d9400b3b))
* **order-numbers:** stop concurrent checkouts looping on MySQL ([fc532f1](https://github.com/Larasell-dev/larasell/commit/fc532f154823af8e655ddae6bca685e43063d799))
* store SEO paths of any length and number duplicates without a limit ([22809ce](https://github.com/Larasell-dev/larasell/commit/22809ce27a64dd62440bc4046956b10b3ce54495))
* store SEO URLs by resource type key, with one canonical row per page ([421e4f9](https://github.com/Larasell-dev/larasell/commit/421e4f9ba4e5c76eaf095f6a20e7b4c825660d98))
* **storefront:** leave fallback routes below a fixed path alone ([4a28fa3](https://github.com/Larasell-dev/larasell/commit/4a28fa38aa1bfbead0578a0495d26179949ecbf0))


### Code Refactoring

* add typed IDs and take them in cart add, set and remove ([01027d9](https://github.com/Larasell-dev/larasell/commit/01027d9d5c4885e57783cf50b735f1359face0f0))
* create carts and orders in an explicit sales context ([179de1e](https://github.com/Larasell-dev/larasell/commit/179de1e01f0773742bbccf0130e5a3a1bd75673e))
* move catalog models to Larasell\Larasell\Catalog\Models ([7af94bf](https://github.com/Larasell-dev/larasell/commit/7af94bf9cadcd5285bf8d5dc6441a6f7e5518e70))
* pass typed IDs across module boundaries ([f6450f5](https://github.com/Larasell-dev/larasell/commit/f6450f5ded2c17bbb09d0c1e255c039fbc2a3c4f))
* remove the model registry ([f48676b](https://github.com/Larasell-dev/larasell/commit/f48676bc95d4c686e6998b7c45882702f449ac3a))
* split the storefront off sales channels ([48d0e96](https://github.com/Larasell-dev/larasell/commit/48d0e965158630c01398e4306241669a75e11f47))

### Upgrade guide

#### `Cart::add()`, `set()` and `remove()` take typed IDs instead of numbers

A bare number meant a product ID in `Cart::add()` but a cart line ID in
`Cart::set()` and `Cart::remove()`. Passing the wrong one changed an unrelated
line or added an unrelated product without any error. These methods now take a
`ProductId`, `VariantId` or `CartItemId`, so PHP rejects the mix-up.

##### Who is affected

Apps that pass numbers to these methods. Passing a `Product` or
`ProductVariant` works as before.

##### How to upgrade

```php
use Larasell\Larasell\Carts\CartItemId;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

// Before
$cart->add($request->integer('product'), 2);
$cart->set($request->integer('line'), 3);
$cart->remove($request->integer('line'));

// After
$cart->add(ProductId::fromString($request->input('product')), 2);
$cart->add(VariantId::fromString($request->input('variant')));
$cart->set(CartItemId::fromString($request->input('line')), 3);
$cart->remove(CartItemId::of($item));
```

`fromString()` throws `Larasell\Larasell\Ids\InvalidId` for input that is not a
positive whole number; validate request input before it reaches the cart.

#### Carts and orders need a sales context

Larasell now has sales channels: the places you sell, such as a storefront, a
POS or a marketplace. Carts and orders remember the `SalesContext` they were
made in (channel, locale and currency), in the new columns `sales_channel_id`
and `locale` next to `currency`. A channel without a URL, such as a POS, works
the same way.

A cart or order is created in a context you pass, with `createIn()`. It never
picks one up from whatever context happens to be current, so a cart made in a
command or job cannot silently land in the wrong channel. In a storefront
controller, inject the request's context. The migrations create a `default`
channel from `app.url`, the supported locales and the enabled currencies.

##### Who is affected

Apps that create carts or orders themselves. Orders made by `Checkout` are not
affected: they take the cart's context.

##### How to upgrade

Run the migrations, then create carts with their context:

```php
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\SalesChannels\SalesContext;

// Before
$cart = Cart::query()->create(['currency' => Currency::EUR]);

// After, in a storefront controller
public function store(SalesContext $context)
{
    $cart = Cart::createIn($context);
}

// After, anywhere else
$cart = Cart::createIn(SalesContext::for('default', 'en'));
```

`Cart::query()->create()` without a context now throws a `LogicException`. A
`currency` given in the attributes still wins over the context's, e.g.
`Cart::createIn($context, ['currency' => Currency::EUR])`.

#### Catalog models live in `Larasell\Larasell\Catalog\Models`

The catalog is now a module of its own, so its models moved out of the shared
`Larasell\Larasell\Models` namespace:

| Before | After |
| --- | --- |
| `Larasell\Larasell\Models\Product` | `Larasell\Larasell\Catalog\Models\Product` |
| `Larasell\Larasell\Models\ProductVariant` | `Larasell\Larasell\Catalog\Models\ProductVariant` |
| `Larasell\Larasell\Models\Category` | `Larasell\Larasell\Catalog\Models\Category` |
| `Larasell\Larasell\Models\ProductAttribute` | `Larasell\Larasell\Catalog\Models\ProductAttribute` |
| `Larasell\Larasell\Models\ProductAttributeValue` | `Larasell\Larasell\Catalog\Models\ProductAttributeValue` |
| `Larasell\Larasell\Models\ProductImage` | `Larasell\Larasell\Catalog\Models\ProductImage` |
| `Larasell\Larasell\Models\Relations\Siblings` | `Larasell\Larasell\Catalog\Models\Relations\Siblings` |

Tables and columns are unchanged.

##### Who is affected

Apps that import any of the classes above. Polymorphic relations that stored
one of these class names in a `*_type` column also need their rows updated,
unless the app uses a morph map.

##### How to upgrade

Update the imports:

```php
// Before
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;

// After
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
```

This replaces them across an app on Linux (on macOS, use `sed -i ''`):

```bash
grep -rlE 'Larasell\\Larasell\\Models\\(Product|Category|Relations)' app config database routes tests \
  | xargs sed -i -E 's/Larasell\\Larasell\\Models\\(Product|Category|Relations)/Larasell\\Larasell\\Catalog\\Models\\\1/g'
```

#### Write the catalog through its actions

Products, variants, categories, attributes, attribute values and images are
now written through actions in `Larasell\Larasell\Catalog\Actions`, such as
`CreateProduct`, `UpdateProduct`, `MoveCategory` and `DeleteProduct`. Each
action runs in its own transaction, locks the rows it changes, checks its
rules before writing, and dispatches a domain event from
`Larasell\Larasell\Catalog\Events` (`ProductUpdated`, `CategoryMoved`, …).
Other modules, such as SEO, react to those events inside the same
transaction, so a product and its URL are saved together or not at all.

**Writing a model directly no longer updates anything else.** `$product->update()`,
`Product::create()` and `$category->delete()` still write the row, but no
event is dispatched: SEO URLs are not created, moved, or marked as gone, and
the rules other modules set (no delete of a product with open orders or
reserved stock) are not checked.

##### Who is affected

Apps, imports and seeders that create, change or delete catalog models with
Eloquent. Reading models is unchanged.

##### How to upgrade

Call the action that matches the change. Actions take IDs and plain input:

```php
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Actions\MoveCategory;
use Larasell\Larasell\Catalog\Actions\SetProductCategories;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;

// Before
$product = Product::create(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
$product->categories()->attach($shoes);
$product->update(['name' => ['en' => 'Leather Boots']]);
$boots->update(['parent_id' => $sale->id]);
$product->delete();

// After
$product = app(CreateProduct::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
app(SetProductCategories::class)->handle($product->id, [$shoes->id]);
app(UpdateProduct::class)->handle($product->id, ['name' => ['en' => 'Leather Boots']]);
app(MoveCategory::class)->handle($boots->id, $sale->id);
app(DeleteProduct::class)->handle($product->id);
```

`UpdateCategory` no longer accepts `parent_id` or `position`; use
`MoveCategory`, which refuses to put a category below itself or one of its
descendants (`CategoryCycle`). `DeleteProduct` throws `ProductInUse` while
an open order contains the product or units of it are reserved.

After bulk writes that bypassed the actions, bring the URLs up to date:

```bash
php artisan larasell:regenerate-seo-urls
```

#### Entry points serve a locale; the currency comes from the visit

An entry point (a host plus an optional path prefix) now serves one locale,
not one locale and one currency. Tying a URL to a currency meant a shop
selling in EUR and CHF needed a domain per currency, which search engines see
as duplicate pages. A visit starts in the entry point's optional
`default_currency`, else the channel's default, and can switch to any other
currency the channel sells in through the same URL.

##### Who is affected

Apps that call `AddEntryPoint`, read `EntryPoint::$currency` or the
`currency` column of `larasell_storefront_entry_points`, or listen to
`EntryPointCreated`. Apps that only use `StorefrontContext` are not affected.

##### How to upgrade

| Before | After |
| --- | --- |
| `AddEntryPoint` input `currency` | `default_currency` (optional) |
| `EntryPoint::$currency`, column `currency` | `EntryPoint::$defaultCurrency`, nullable column `default_currency` |
| `EntryPointCreated::$currency` | `EntryPointCreated::$defaultCurrency` (nullable) |
| one primary entry point per channel, locale and currency | one primary entry point per channel and locale |
| a currency needs its own entry point | `$storefront->withCurrency(Currency::EUR)` on the same entry point |

```php
// Before
$addEntryPoint->handle($channelId, ['url' => 'https://shop.ch', 'locale' => 'de', 'currency' => 'CHF']);

// After
$addEntryPoint->handle($channelId, ['url' => 'https://shop.ch', 'locale' => 'de', 'default_currency' => 'CHF']);
$storefront = $storefront->withCurrency(Currency::EUR); // the visitor picked EUR
```

Where the visitor's pick comes from, such as a switcher or their cart's
`currency`, is up to the app: call `withCurrency()` with it.

#### Larasell's models can no longer be swapped

The `larasell.models` and `larasell.seo.models` config keys are gone, together
with `Larasell\Larasell\Models\ModelRegistry` and `ModelEntry`. Core code now
always uses its own models, such as `Product` and `Order`.

Swapping a model let an app change core behaviour from the outside, and every
module had to look its models up at runtime. That made identities unstable (a
swapped product got new SEO URLs) and made module boundaries impossible to
check. Apps now keep their own data in their own models and link them to core
records by ID.

##### Who is affected

Apps that set `larasell.models.*` or `larasell.seo.models.seo_url` in
`config/larasell.php` or `config/larasell/seo.php`, or that resolve
`ModelRegistry` from the container. Apps that never swapped a model are not
affected; you may delete the `models` section from published config files.

##### How to upgrade

Remove the `models` section from your published config. Move the fields and
relations your subclass added into a model of your own with a foreign key to
the core table:

```php
// Before: config/larasell.php
'models' => [
    'product' => App\Models\Product::class, // extends Larasell\Larasell\Catalog\Models\Product
],

// After: a model of your own, linked by product ID
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Catalog\Models\Product;

class ProductDetails extends Model
{
    protected $fillable = ['product_id', 'care_instructions'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

// Read it next to the product
$details = ProductDetails::query()->where('product_id', $product->id)->first();
```

```php
// Migration for the app's own table
Schema::create('product_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->unique()->constrained('larasell_products')->cascadeOnDelete();
    $table->text('care_instructions')->nullable();
    $table->timestamps();
});
```

To react to changes of core records, listen to their domain events, such as
`Larasell\Larasell\Catalog\Events\ProductDeleted`, instead of overriding model
methods.

#### Product and category slugs are replaced by SEO URLs

The `slug` columns of `larasell_products` and `larasell_categories` are
dropped, together with the routing built on them. Storefront URLs now live in
the new SEO module (`larasell_seo_urls`): each product and category gets a URL
per channel and locale, renames keep the old URL as a redirect, and deleted
pages answer 410 or redirect to a replacement.

**Existing slugs are not carried over.** After migrating, products live at
`p/{name}` and categories at `{parent path}/{name}`, built from their names.
Links to old slug URLs answer 404 unless you redirect them yourself.

##### Who is affected

Apps that use any of these:

- the `slug` attribute of `Product` or `Category`, including `'slug' => …` in
  `create()` or `update()` calls and queries such as `where('slug->en', …)`;
- `Larasell\Larasell\Routing\ProductDetailRoute`,
  `Larasell\Larasell\Routing\ProductListingRoute`, or
  `Larasell\Larasell\Routing\CategoryUrl`;
- `Larasell\Larasell\Http\Requests\ProductDetailRequest`;
- `ProductListingRequest` with a `{category}` slug path;
- `OrderItem::$product_slug`. Checkout no longer writes it; the column stays,
  so orders placed before the upgrade keep their value.

Attribute and attribute value slugs (`ProductAttribute::$slug`,
`ProductAttributeValue::$slug`) are unchanged.

##### How to upgrade

Run the migrations, then create URLs for your existing products and
categories:

```bash
php artisan migrate
php artisan larasell:regenerate-seo-urls
```

Stop passing `slug` when creating products and categories, and create them
through the catalog's actions, which give them their URLs (see
`catalog-writes-through-actions.md`):

```php
// Before
Product::create(['slug' => 'boots', 'name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);

// After
app(CreateProduct::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
```

Register storefront routes inside `Route::storefront()`. Typed parameters are
resolved from SEO URLs, and `Route::seo()` serves categories at any depth and
custom URLs:

```php
// Before
use Larasell\Larasell\Routing\ProductDetailRoute;
use Larasell\Larasell\Routing\ProductListingRoute;

ProductDetailRoute::get(ShowProduct::class, prefix: 'p');
ProductListingRoute::get(ShowCategory::class, prefix: 'c');

// After
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;

Route::storefront(function () {
    Route::get('p/{product}', ShowProduct::class);
    Route::seo([Category::class => ShowCategory::class]);
});
```

Controllers that used `ProductDetailRequest` take the product directly.
`ProductListingRequest` keeps working and reads the category the route bound:

```php
// Before
public function __invoke(ProductDetailRequest $request)
{
    $product = $request->product();
}

// After
public function __invoke(Product $product) {}
```

Generate links with the `Seo` facade instead of `CategoryUrl` or slugs:

```php
// Before
CategoryUrl::make($category);
url('p/'.$product->slug->get());

// After
use Larasell\Larasell\Seo\Facades\Seo;

Seo::url($category);
Seo::url($product);
```

Deleted and hidden pages throw `SeoPageGone` (410) and `SeoPageHidden` (404).
Render them like any exception, e.g. in `bootstrap/app.php`:

```php
use Larasell\Larasell\Seo\Exceptions\SeoPageGone;

$exceptions->render(fn (SeoPageGone $e) => response()->view('discontinued', $e->snapshot, 410));
```

#### Absolute URLs need a `StorefrontContext`

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

##### Who is affected

Apps that build URLs with a context they pass themselves, implement a gone
strategy or hidden page policy, or create channels and entry points in
seeders. Storefront controllers that rely on the current request's context
keep working.

##### How to upgrade

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

#### Actions, events, jobs and sales contexts use typed IDs

The catalog, sales channel and storefront actions, the workflows, and the jobs
that release expired orders now take typed IDs such as `ProductId` or
`CategoryId` instead of bare numbers. Their events and `SalesContext`
carry typed IDs too. A number meant for one kind of record can no longer be
passed where another is expected, e.g. a variant ID to `DeleteProduct`.

Each ID class wraps a positive `int` (`->value`) and is named after its record:

| Module | IDs |
| --- | --- |
| Catalog (`Larasell\Larasell\Catalog`) | `ProductId`, `VariantId`, `CategoryId`, `AttributeId`, `AttributeValueId`, `ImageId` |
| Sales channels (`Larasell\Larasell\SalesChannels`) | `SalesChannelId` |
| Storefront (`Larasell\Larasell\Storefront`) | `EntryPointId` |
| Carts (`Larasell\Larasell\Carts`) | `CartId`, `CartItemId` |
| Checkout (`Larasell\Larasell\Checkout`) | `OrderId` |

Models keep their `int` keys; nothing changes inside a module.

##### Who is affected

Apps that call these actions or workflows, listen to their events, construct
`SalesContext` or `EntryPoint` themselves, or pass a channel ID to
`SalesContext::for()`.

##### How to upgrade

Wrap IDs where you call an action, and read `->value` where you need the number:

```php
use Larasell\Larasell\Catalog\Actions\SetProductCategories;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\ProductId;

// Before
app(UpdateProduct::class)->handle($product->id, ['sku' => 'B-2']);
app(SetProductCategories::class)->handle($product->id, [$shoes->id, $sale->id]);

// After
app(UpdateProduct::class)->handle(ProductId::of($product), ['sku' => 'B-2']);
app(SetProductCategories::class)->handle(ProductId::of($product), CategoryId::many([$shoes->id, $sale->id]));

// From request input; throws Larasell\Larasell\Ids\InvalidId for anything but a positive whole number
app(UpdateProduct::class)->handle(ProductId::fromString($request->route('product')), $attributes);
```

In listeners, compare IDs with `equals()` or their `->value`, never with `===`,
which compares instances:

```php
// Before
if ($event->productId === $product->id) { /* ... */ }

// After
if ($event->productId->value === $product->id) { /* ... */ }
if ($event->productId->equals(ProductId::of($product))) { /* ... */ }
```

`CreateProductVariant` now returns a `VariantId` and `GenerateProductVariants`
a `list<VariantId>`. `SalesContext::for()` and `StorefrontContext::for()` take
a channel key or a `SalesChannelId`, no longer an `int`.
`ReleaseExpiredInventoryForOrder` and `ReleaseExpiredPromotionRedemptionsForOrder`
take an `OrderId`. In `CartMerged`, `$sourceCartId` is a `CartId`; in
`CartMergeLineAdjustment`, `$variantId` is a `VariantId`.

## [1.23.0](https://github.com/Larasell-dev/larasell/compare/v1.22.0...v1.23.0) (2026-11-03)


### ⚠ BREAKING CHANGES

* upgrade Chronicle to 0.4
* require PHP 8.4

### Features

* upgrade Chronicle to 0.4 ([1aec70d](https://github.com/Larasell-dev/larasell/commit/1aec70ddc1f7f33f9fff66181bb71b9fc5906280))


### Build System

* require PHP 8.4 ([bc8bb56](https://github.com/Larasell-dev/larasell/commit/bc8bb56b5931d79ffe82a99a9eef8558594d5080))

### Upgrade guide

#### Larasell uses Chronicle 0.4

Larasell's structured exceptions, `LicenseException` and `UnknownEntitlement`, now implement
Chronicle 0.4's `Larasell\Chronicle\Exceptions\StructuredException` instead of
`Larasell\Chronicle\StructuredError`. Chronicle 0.4 bounds and redacts every event it logs and adds
a fake for tests, and Larasell needs it so apps and the admin panel can upgrade too.

##### Who is affected

- Apps that catch Larasell's exceptions as `Larasell\Chronicle\StructuredError`.
- Apps that use Chronicle themselves, e.g. its `LogRequests` middleware or structured exceptions.
  Composer now installs Chronicle 0.4, so follow
  [Chronicle's upgrade guide](https://github.com/Larasell-dev/chronicle/blob/main/CHANGELOG.md).

Apps that catch `LicenseException` or `UnknownEntitlement` by name, or only read `errorCode()`,
`errorWhy()`, `errorFix()` and `errorLink()`, are not affected.

##### How to upgrade

Catch the new interface:

```php
// Before
use Larasell\Chronicle\StructuredError;

try {
    $license->activate($key);
} catch (StructuredError $exception) {
    report($exception);
}

// After
use Larasell\Chronicle\Exceptions\StructuredException;

try {
    $license->activate($key);
} catch (StructuredException $exception) {
    report($exception);
}
```

#### Larasell requires PHP 8.4

Larasell now needs PHP 8.4 or newer. Chronicle, the logging package Larasell is built on, requires
PHP 8.4 from version 0.3.0, and Larasell's CI already runs on PHP 8.4 only, so PHP 8.3 was
untested.

##### Who is affected

Apps that run on PHP 8.3. Composer won't install this version of Larasell there. Apps on PHP 8.4
or 8.5 are not affected.

##### How to upgrade

Upgrade PHP to 8.4 or newer on every server and in CI, then raise the PHP requirement in your
app's `composer.json`:

```json
// Before
"require": {
    "php": "^8.3"
}

// After
"require": {
    "php": "^8.4"
}
```

## [1.22.0](https://github.com/Larasell-dev/larasell/compare/v1.21.0...v1.22.0) (2026-11-02)


### ⚠ BREAKING CHANGES

* products no longer allow backorders unless allow_backorders is set to true.

### Features

* add a position to categories to define their order ([c51fe51](https://github.com/Larasell-dev/larasell/commit/c51fe51b26544e0e44b59c6ef254af4119f8669a))
* disable backorders by default ([b0a7fc8](https://github.com/Larasell-dev/larasell/commit/b0a7fc8068aa8156deaa7096cf8087c59fc1b196))

### Upgrade guide

#### Products no longer allow backorders by default

`larasell_products.allow_backorders` now defaults to `false` instead of `true`,
so a product created without `allow_backorders` can't be sold beyond its stock.
Allowing overselling should be an explicit choice, not an accident.

##### Who is affected

Apps that create products without setting `allow_backorders` and rely on them
accepting orders when stock runs out. Existing products keep their stored
value; only products created after running the migration are affected. Apps
that always set `allow_backorders` explicitly are not affected.

##### How to upgrade

Run the new migration:

```bash
php artisan migrate
```

Then pass `allow_backorders` wherever products should still accept backorders:

```php
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

// Before
Product::create([
    'slug' => 'gift-card',
    'name' => 'Gift card',
    'price' => Price::of(2500),
]);

// After
Product::create([
    'slug' => 'gift-card',
    'name' => 'Gift card',
    'price' => Price::of(2500),
    'allow_backorders' => true,
]);
```

## [1.21.0](https://github.com/Larasell-dev/larasell/compare/v1.20.0...v1.21.0) (2026-11-02)


### ⚠ BREAKING CHANGES

* ProductAttribute::name and ProductAttributeValue::name are Translatable instead of string.
* Locale enum values use hyphens instead of underscores, and Translatable falls back to larasell.locale.default instead of app.fallback_locale. A migration renames stored translation keys.

### Features

* make product attribute and value names translatable ([#145](https://github.com/Larasell-dev/larasell/issues/145)) ([d144986](https://github.com/Larasell-dev/larasell/commit/d144986bebb60deb16584123b1e9af984c33d3ab))
* use BCP 47 locale tags and configure supported locales ([#144](https://github.com/Larasell-dev/larasell/issues/144)) ([a1cf928](https://github.com/Larasell-dev/larasell/commit/a1cf928fdde99d1cccaf01dd654fef30b0118d7f))

### Upgrade guide

#### Product attribute and value names are now `Translatable`

`ProductAttribute::$name` and `ProductAttributeValue::$name` are now
`Larasell\Larasell\Translatable` instances instead of strings, like product and
category names. Variant options such as "Color" and "Red" can now be shown in
the visitor's language.

##### Who is affected

Code that reads `name` on a `ProductAttribute` or `ProductAttributeValue` and
treats it as a string, for example by printing it, comparing it, or calling
`pluck('name')`.

`ProductVariant::options()` and `ProductVariant::name()` keep returning strings,
now in the current locale. Code that only uses them is not affected. Writing
plain strings keeps working: they are stored for the current locale.

##### How to upgrade

Run the migrations. They turn every existing name into a translation for
`larasell.locale.default`.

```bash
php artisan migrate
```

Call `get()` where you need the text:

```php
// Before
$attribute->name; // 'Color'
$attribute->values()->pluck('name');

// After
$attribute->name->get(); // 'Color', or 'Farbe' when the locale is 'de'
$attribute->values->map(fn ($value) => $value->name->get());
```

Translate names the same way as product names:

```php
use Larasell\Larasell\Models\ProductAttribute;

ProductAttribute::create(['slug' => 'color', 'name' => ['en' => 'Color', 'de' => 'Farbe']]);
```

#### `Locale` values are now BCP 47 tags (`de-DE` instead of `de_DE`)

Every value of `Larasell\Larasell\Enums\Locale` that has a region or script now
uses a hyphen, as in `de-DE`, `zh-Hans` and `es-419`. Translation keys are stored
the same way. This is the format HTTP headers, HTML `lang` and `hreflang` use, so
no conversion is needed at the HTTP boundary. Script locales like `zh-Hans`,
which `Translatable` used to reject, now work.

##### Who is affected

- Code that stores, compares or hard-codes `Locale->value`, or calls
  `Locale::from()` with an underscore value such as `'de_DE'`.
- Code that reads translation JSON (`name`, `slug`, `description`,
  `product_name`) directly from the database, or queries it with paths like
  `slug->de_DE`.

Apps that only use base locales such as `en` and `de` are not affected: their
values did not change. `App::setLocale('de_DE')` keeps working, and
`Translatable` accepts underscore keys and stores them as hyphens.

##### How to upgrade

Run the migration. It renames stored translation keys from `de_DE` to `de-DE`
and can be rolled back.

```bash
php artisan migrate
```

Replace underscore values in your code, or canonicalize input first:

```php
use Larasell\Larasell\Enums\Locale;

// Before
Locale::from('de_DE');
$query->where('slug->de_DE', $slug);

// After
Locale::from('de-DE');
Locale::from(Locale::canonicalize('de_DE')); // for input in any case or style
$query->where('slug->de-DE', $slug);
```

#### Translations fall back to `larasell.locale.default`, not `app.fallback_locale`

When a translation is missing in the requested locale, `Translatable::get()` now
falls back to the shop's default locale from the new
`config/larasell/locale.php`. Plain string values read from the database are
stored under that locale too. This gives a shop one setting for its default
language, next to the list of locales it supports.

##### Who is affected

Apps that set `app.fallback_locale` to something other than `en`. Apps whose
`app.fallback_locale` is `en` are not affected, because
`larasell.locale.default` also defaults to `en`.

##### How to upgrade

Publish the config and set the default to your previous `app.fallback_locale`.
The default must also be in `supported`.

```bash
php artisan vendor:publish --tag=larasell-config
```

```php
// Before: config/app.php
'fallback_locale' => 'de',

// After: config/larasell/locale.php
use Larasell\Larasell\Enums\Locale;

return [
    'supported' => [Locale::GERMAN, Locale::ENGLISH],
    'default' => Locale::GERMAN,
];
```

## [1.20.0](https://github.com/Larasell-dev/larasell/compare/v1.19.1...v1.20.0) (2026-11-01)


### Features

* add licensing and plan entitlements ([ba856df](https://github.com/Larasell-dev/larasell/commit/ba856df0c9ba9a3735e055226ff3e4c955be2b7e))

## [1.19.1](https://github.com/Larasell-dev/larasell/compare/v1.19.0...v1.19.1) (2026-10-13)


### Bug Fixes

* remove admin panel ([941c143](https://github.com/Larasell-dev/larasell/commit/941c14330938f7c6bde6fdf108b4a76348bc9379))

## [1.19.0](https://github.com/Larasell-dev/larasell/compare/v1.18.2...v1.19.0) (2026-10-13)


### Features

* support custom order generators ([#135](https://github.com/Larasell-dev/larasell/issues/135)) ([264b9ea](https://github.com/Larasell-dev/larasell/commit/264b9ea9e42cc1e18000ce22cd2f8001a5514220))

## [1.18.2](https://github.com/Larasell-dev/larasell/compare/v1.18.1...v1.18.2) (2026-09-27)


### Bug Fixes

* move starter kit into separate repo ([#132](https://github.com/Larasell-dev/larasell/issues/132)) ([d46f79c](https://github.com/Larasell-dev/larasell/commit/d46f79c17e58aab951cb20f4a262824a4318e5d9))

## [1.18.1](https://github.com/Larasell-dev/larasell/compare/v1.18.0...v1.18.1) (2026-09-27)


### Bug Fixes

* accept file instead of path when storing product images ([#128](https://github.com/Larasell-dev/larasell/issues/128)) ([045675e](https://github.com/Larasell-dev/larasell/commit/045675eba6697d8dcc3f0b93d5e77868de8fde93))
* make siblings a relation ([#131](https://github.com/Larasell-dev/larasell/issues/131)) ([a695e49](https://github.com/Larasell-dev/larasell/commit/a695e49396742ba4a91c2492a9ae4324fe499bf1))

## [1.18.0](https://github.com/Larasell-dev/larasell/compare/v1.17.0...v1.18.0) (2026-09-16)


### Features

* add barcode value object ([#126](https://github.com/Larasell-dev/larasell/issues/126)) ([ea7fafb](https://github.com/Larasell-dev/larasell/commit/ea7fafbd08679f82874851afb17f192bf966754c))

## [1.17.0](https://github.com/Larasell-dev/larasell/compare/v1.16.0...v1.17.0) (2026-09-16)


### Features

* add Locale enum ([#124](https://github.com/Larasell-dev/larasell/issues/124)) ([4d5798f](https://github.com/Larasell-dev/larasell/commit/4d5798fab9dd5c9717a18345fd0b9f6a6c4e39ca))

## [1.16.0](https://github.com/Larasell-dev/larasell/compare/v1.15.0...v1.16.0) (2026-09-15)


### Features

* add more arithmetic methods to prices ([#123](https://github.com/Larasell-dev/larasell/issues/123)) ([371e1a8](https://github.com/Larasell-dev/larasell/commit/371e1a81a998a4b001e8c5aaa75327aee0dd7326))
* add more currencies ([#120](https://github.com/Larasell-dev/larasell/issues/120)) ([9ce353d](https://github.com/Larasell-dev/larasell/commit/9ce353d15dee5703569c3a51b81e5831e09fe47e))

## [1.15.0](https://github.com/Larasell-dev/larasell/compare/v1.14.1...v1.15.0) (2026-09-11)


### Features

* add image placeholders ([#119](https://github.com/Larasell-dev/larasell/issues/119)) ([69def3b](https://github.com/Larasell-dev/larasell/commit/69def3b1c98a92fe019ffe3d5f492a3c422eb074))
* add thumbnail helper to products ([#116](https://github.com/Larasell-dev/larasell/issues/116)) ([ca6cf44](https://github.com/Larasell-dev/larasell/commit/ca6cf4405066b1623c05546572a2b74392cc4029))


### Bug Fixes

* do not cache cart prefetch ([#113](https://github.com/Larasell-dev/larasell/issues/113)) ([4708c76](https://github.com/Larasell-dev/larasell/commit/4708c76c14358e4000efaf92a46fb2940aeb1eb8))
* invalidate prefixed pages if cart count changes ([#115](https://github.com/Larasell-dev/larasell/issues/115)) ([8742f2b](https://github.com/Larasell-dev/larasell/commit/8742f2beb69c1a3fa30527d91d639267511fb934))

## [1.14.1](https://github.com/Larasell-dev/larasell/compare/v1.14.0...v1.14.1) (2026-09-10)


### Bug Fixes

* keep quantity when adding product to cart ([#111](https://github.com/Larasell-dev/larasell/issues/111)) ([bb8893d](https://github.com/Larasell-dev/larasell/commit/bb8893d22e0f0e8fd126bd9f45cdd579ec0ced24))
* let inertia decide on rendering mechanism ([#109](https://github.com/Larasell-dev/larasell/issues/109)) ([c01af60](https://github.com/Larasell-dev/larasell/commit/c01af60b499fd095e93a37ccbe9b3bc587bca324))


### Performance Improvements

* prefetch cart, product and category pages ([#112](https://github.com/Larasell-dev/larasell/issues/112)) ([d87370b](https://github.com/Larasell-dev/larasell/commit/d87370be4fbb47b62e0710f0668197efadfd7fff))

## [1.14.0](https://github.com/Larasell-dev/larasell/compare/v1.13.0...v1.14.0) (2026-09-10)


### Features

* add cart merge ([#106](https://github.com/Larasell-dev/larasell/issues/106)) ([ad416b3](https://github.com/Larasell-dev/larasell/commit/ad416b3b3cdb47458fdec746b1579d62b912a208))


### Bug Fixes

* make starter kit ssr compatible ([#108](https://github.com/Larasell-dev/larasell/issues/108)) ([7fbdba6](https://github.com/Larasell-dev/larasell/commit/7fbdba63675863b63cf807a81c1dc4c3a8ef40f4))

## [1.13.0](https://github.com/Larasell-dev/larasell/compare/v1.12.0...v1.13.0) (2026-09-10)


### Features

* add cart to starter kit ([9f63ed5](https://github.com/Larasell-dev/larasell/commit/9f63ed5f0e52b4bf0f8518258f2ce1ec21d207ad))
* add checkout to starter kit ([dd7c46a](https://github.com/Larasell-dev/larasell/commit/dd7c46a45858bc287611bc763b0b3a17a1e7da7a))
* add compare at price ([885fa5b](https://github.com/Larasell-dev/larasell/commit/885fa5bad9dacd05a5527324195c2cd51d0d65de))
* add customers ([02b49b0](https://github.com/Larasell-dev/larasell/commit/02b49b086e56a7366ce93dcd3b35afde817fa41e))
* add explicit exceptions for promotions ([e37cb57](https://github.com/Larasell-dev/larasell/commit/e37cb5742a3c05140336acbe5e11edf87874160b))
* add i18n to starter kit ([#82](https://github.com/Larasell-dev/larasell/issues/82)) ([58ad661](https://github.com/Larasell-dev/larasell/commit/58ad66120c446136724444407f2a9deca170f72f))
* add method to fetch visible variants easily ([1fc49e0](https://github.com/Larasell-dev/larasell/commit/1fc49e08d201797671438e6e5b4aa2dbdf6cf96a))
* add optional billing address form ([a30e093](https://github.com/Larasell-dev/larasell/commit/a30e093c2469312bbceafd493aacf2e5b3f99267))
* add order history ([8ae2e95](https://github.com/Larasell-dev/larasell/commit/8ae2e95abf88d9ac5c5e5b13223f84754668c5c0))
* add order related mails ([6cc37b2](https://github.com/Larasell-dev/larasell/commit/6cc37b2de7d953b6bd6b08715cab59a7da31a9c8))
* add products to cart by id ([#90](https://github.com/Larasell-dev/larasell/issues/90)) ([f283f57](https://github.com/Larasell-dev/larasell/commit/f283f57861a75ea52e7f50e63c8a4f321bd2f631))
* add promotion codes to the storefront ([f140ab0](https://github.com/Larasell-dev/larasell/commit/f140ab07c85e3fd439fea5b309aaf5eb49d6bb3a))
* add styles to starter kit ([#105](https://github.com/Larasell-dev/larasell/issues/105)) ([e23acfc](https://github.com/Larasell-dev/larasell/commit/e23acfc4e46844faae8ac47ce8cf605759eda076))
* add weight and dimension ([958fe25](https://github.com/Larasell-dev/larasell/commit/958fe2553b0dc4f6782865187a6fdc4f2431b0b0))
* display variants on starter kit ([69a9620](https://github.com/Larasell-dev/larasell/commit/69a96203c0779a5acd34af54287f51eece55fd2c))
* get discounted total for a CartItem ([5273f1f](https://github.com/Larasell-dev/larasell/commit/5273f1fde4237611b457602b285ccf28a7ac96d9))
* make starter kit multi lang ([#84](https://github.com/Larasell-dev/larasell/issues/84)) ([6bec9d1](https://github.com/Larasell-dev/larasell/commit/6bec9d1507d5577150dcb223bf6f9db228565e51))
* prefill address when ordering with an account ([e1ea5fb](https://github.com/Larasell-dev/larasell/commit/e1ea5fb9e689260ac554ac2fa2cd3606df6c106b))
* show discount for each cart item in the starter kit ([bb86a6d](https://github.com/Larasell-dev/larasell/commit/bb86a6d625a8c12ef45eb5a42397e946557b29ab))
* show tax and shipping on starter kit ([af83c69](https://github.com/Larasell-dev/larasell/commit/af83c693f4b5590038300680ac16d178101c98ed))


### Bug Fixes

* display empty state on cart page ([8d36bee](https://github.com/Larasell-dev/larasell/commit/8d36beeeda5ea688294ab0f6190272c4c5960caa))
* load option relations inside ProductVariant::options() ([df8e205](https://github.com/Larasell-dev/larasell/commit/df8e20573951210a9a402e1e94db44536a6d33a4))
* remove all related stuff from the starter kit ([#85](https://github.com/Larasell-dev/larasell/issues/85)) ([3170dc7](https://github.com/Larasell-dev/larasell/commit/3170dc749f2d00b82b89f5b7e2707ac506894e78))
* remove multi locale stuff from starter kit ([#89](https://github.com/Larasell-dev/larasell/issues/89)) ([daea92a](https://github.com/Larasell-dev/larasell/commit/daea92ae764f84407b5bef3529fad63e81859a10))
* rename optionSnapshots to options ([00e0eff](https://github.com/Larasell-dev/larasell/commit/00e0effb29610b813850f6f2c52a6ed20c0c441d))
* rename snapshotName to name ([807d49d](https://github.com/Larasell-dev/larasell/commit/807d49dbae92c71540827eeb010f5b415c030d49))

## [1.12.0](https://github.com/Larasell-dev/larasell/compare/v1.11.0...v1.12.0) (2026-09-03)


### Features

* add cart exceptions ([#81](https://github.com/Larasell-dev/larasell/issues/81)) ([542d5e2](https://github.com/Larasell-dev/larasell/commit/542d5e230a686d8ba2586e837050c9ab046b223f))
* add footer to starter kit ([#80](https://github.com/Larasell-dev/larasell/issues/80)) ([3d08574](https://github.com/Larasell-dev/larasell/commit/3d085747b73e9213a6d1339829fc3b41825f4351))
* add menu to starter kit ([#79](https://github.com/Larasell-dev/larasell/issues/79)) ([9234ed3](https://github.com/Larasell-dev/larasell/commit/9234ed39a67041910a31ecedad05a8eb8dd5b623))
* add product listing page to starter kit ([#78](https://github.com/Larasell-dev/larasell/issues/78)) ([720e6b4](https://github.com/Larasell-dev/larasell/commit/720e6b454a82c0ed68ed992bcf494f6eb65dca9d))
* add public id to orders ([93ec712](https://github.com/Larasell-dev/larasell/commit/93ec712ec30183de1dc7e5ed117940d74a14b2a0))
* add starter kit ([#76](https://github.com/Larasell-dev/larasell/issues/76)) ([60cb6db](https://github.com/Larasell-dev/larasell/commit/60cb6db273bf9297226b9f3f64437b89707aed2e))


### Bug Fixes

* format prices on order confirmation page on the server ([af604fc](https://github.com/Larasell-dev/larasell/commit/af604fc695a54d5d1dbf127fc1ab74da3c2aae91))

## [1.11.0](https://github.com/Larasell-dev/larasell/compare/v1.10.0...v1.11.0) (2026-09-01)


### Features

* provide breakdown to payment provider ([02aa057](https://github.com/Larasell-dev/larasell/commit/02aa057a4c8b52d3d99cd57a847dc9c17cd8d459))

## [1.10.0](https://github.com/Larasell-dev/larasell/compare/v1.9.0...v1.10.0) (2026-09-01)


### Features

* add taxes ([#67](https://github.com/Larasell-dev/larasell/issues/67)) ([8093bce](https://github.com/Larasell-dev/larasell/commit/8093bce780ed6fe2dcbf43fe5bd46b95d56e0679))

## [1.9.0](https://github.com/Larasell-dev/larasell/compare/v1.8.0...v1.9.0) (2026-09-01)


### Features

* add line item metadata ([eaf3973](https://github.com/Larasell-dev/larasell/commit/eaf3973f98872611f323a59afeb425b493813353))

## [1.8.0](https://github.com/Larasell-dev/larasell/compare/v1.7.2...v1.8.0) (2026-09-01)


### Features

* add cart metadata ([020d4e1](https://github.com/Larasell-dev/larasell/commit/020d4e1f7854acb9c254d98a3cae4db7276cc5ed))

## [1.7.2](https://github.com/Larasell-dev/larasell/compare/v1.7.1...v1.7.2) (2026-08-31)


### Bug Fixes

* reduce size of composer package ([cd0d4a4](https://github.com/Larasell-dev/larasell/commit/cd0d4a402a480c132f29f323ac8539d780bc99da))

## [1.7.1](https://github.com/Larasell-dev/larasell/compare/v1.7.0...v1.7.1) (2026-08-31)


### Bug Fixes

* store translated product titles in order ([f03be0b](https://github.com/Larasell-dev/larasell/commit/f03be0bd0cf6de18f811c9e546ce33dcdbdb24db))

## [1.7.0](https://github.com/Larasell-dev/larasell/compare/v1.6.0...v1.7.0) (2026-08-30)


### Features

* make slugs translatable ([fb86ed5](https://github.com/Larasell-dev/larasell/commit/fb86ed5757f63198a5743c7ac35126a48fc23e85))
* translatable category titles ([#57](https://github.com/Larasell-dev/larasell/issues/57)) ([03fb996](https://github.com/Larasell-dev/larasell/commit/03fb996f0211235784e7cecbc595d1fce7968065))

## [1.6.0](https://github.com/Larasell-dev/larasell/compare/v1.5.0...v1.6.0) (2026-08-30)


### Features

* add scopes to query products by category easier ([c0c4c08](https://github.com/Larasell-dev/larasell/commit/c0c4c0892ff399d252fc56ea96e7fc9d321e0bed))

## [1.5.0](https://github.com/Larasell-dev/larasell/compare/v1.4.1...v1.5.0) (2026-08-30)


### Features

* add checkout idempotency ([e18f56b](https://github.com/Larasell-dev/larasell/commit/e18f56b7118f3cc831c62e6c96b85b0233833f80))
* add product variants ([#52](https://github.com/Larasell-dev/larasell/issues/52)) ([e8ab659](https://github.com/Larasell-dev/larasell/commit/e8ab659c8fe02a9ae697801d418f9890a3244c37))
* add sku and barcodes ([#51](https://github.com/Larasell-dev/larasell/issues/51)) ([8789a3f](https://github.com/Larasell-dev/larasell/commit/8789a3fb74e781db0efb319460e29eb63d32c7f9))

## [1.4.1](https://github.com/Larasell-dev/larasell/compare/v1.4.0...v1.4.1) (2026-08-30)


### Bug Fixes

* specify requirements ([0835e52](https://github.com/Larasell-dev/larasell/commit/0835e529261a76aac26dd43187acd7f736c77345))

## [1.4.0](https://github.com/Larasell-dev/larasell/compare/v1.3.0...v1.4.0) (2026-08-30)


### Features

* add discount events ([45a8435](https://github.com/Larasell-dev/larasell/commit/45a8435f9e8be36022b80f499338fd8524256221))
* add priority and exclusivity for promotions ([9897892](https://github.com/Larasell-dev/larasell/commit/98978922f3f6e3c2751523181f4118242962e9a8))
* add promotion api ([#34](https://github.com/Larasell-dev/larasell/issues/34)) ([55d1e7a](https://github.com/Larasell-dev/larasell/commit/55d1e7a78ed80dbbb1de7cf2a0189cfcd0df1590))
* add promotion effects ([#36](https://github.com/Larasell-dev/larasell/issues/36)) ([2d16d25](https://github.com/Larasell-dev/larasell/commit/2d16d25fe50a696316bc6e24797b32d0c9851499))
* add promotion limits ([#46](https://github.com/Larasell-dev/larasell/issues/46)) ([1c42670](https://github.com/Larasell-dev/larasell/commit/1c4267072b96f45d1140ade2865510222a4ea8fa))
* add promotions ([#33](https://github.com/Larasell-dev/larasell/issues/33)) ([d486184](https://github.com/Larasell-dev/larasell/commit/d48618431f70bd45b77c19d257675f4e6a8ec1ee))
* add user entered promotion codes ([#39](https://github.com/Larasell-dev/larasell/issues/39)) ([66f9c95](https://github.com/Larasell-dev/larasell/commit/66f9c95db418d95c3aa22822895b06565f09a1ba))
* calculate promotions ([#37](https://github.com/Larasell-dev/larasell/issues/37)) ([7919935](https://github.com/Larasell-dev/larasell/commit/7919935f6d8322c13efb3eb52512fe066386b13e))
* snapshot discounts ([#38](https://github.com/Larasell-dev/larasell/issues/38)) ([f2036f4](https://github.com/Larasell-dev/larasell/commit/f2036f4dac415508d320cd8c136cfc94675af870))

## [1.3.0](https://github.com/Larasell-dev/larasell/compare/v1.2.0...v1.3.0) (2026-08-29)


### Features

* add expired inventory cleanup command ([76aa531](https://github.com/Larasell-dev/larasell/commit/76aa531d4b5fdbfdd3681330985d670d3d6eef63))
* add inventory events ([2587b56](https://github.com/Larasell-dev/larasell/commit/2587b56571ae9945ef7b1de398476f2d22d6987a))
* add inventory reservation lifecycle events ([3ee7648](https://github.com/Larasell-dev/larasell/commit/3ee76489e04fcfdaa72a476063d6289e46a939ed))
* add optional company details to addresses ([#19](https://github.com/Larasell-dev/larasell/issues/19)) ([2114346](https://github.com/Larasell-dev/larasell/commit/21143469d6fbb3b522c4e256656e83f239ca84fe))
* add optional company details to addresses ([#22](https://github.com/Larasell-dev/larasell/issues/22)) ([18f87af](https://github.com/Larasell-dev/larasell/commit/18f87af886b3e730ffe11fd782ca97954e36fe86))
* add optional order cancelation reason ([1fa86da](https://github.com/Larasell-dev/larasell/commit/1fa86dafeffcb4e18faa12064796dbdb3b428f39))
* configure inventory reservation expiration ([#26](https://github.com/Larasell-dev/larasell/issues/26)) ([0010f53](https://github.com/Larasell-dev/larasell/commit/0010f53fce28628efda627b612950199e55c3c05))
* consume inventory reservations on payment ([9f4db0b](https://github.com/Larasell-dev/larasell/commit/9f4db0b50ca1b26c94b3f4a5b620e6ec277e620a))
* release expired inventory reservations ([fd94539](https://github.com/Larasell-dev/larasell/commit/fd94539b57d8905210b3c6355c90ec2769ccbb14))
* release inventory reservations on cancellation ([d814741](https://github.com/Larasell-dev/larasell/commit/d814741e911605000d3cc26d04e2c49f7b7359e1))
* reserve inventory during checkout ([b8a2323](https://github.com/Larasell-dev/larasell/commit/b8a2323b8ddefde3ba5a633cfa7cad0c1bcebca8))


### Bug Fixes

* verify product ordering to prevent deadlock ([#23](https://github.com/Larasell-dev/larasell/issues/23)) ([125091f](https://github.com/Larasell-dev/larasell/commit/125091fa5da6c5c901b09ade677c9be065d4217f))

## [1.2.0](https://github.com/Larasell-dev/larasell/compare/v1.1.0...v1.2.0) (2026-08-29)


### Features

* add refunds ([#17](https://github.com/Larasell-dev/larasell/issues/17)) ([c737f8c](https://github.com/Larasell-dev/larasell/commit/c737f8c8cc1437c039ac508003874a68e2365c88))

## [1.1.0](https://github.com/Larasell-dev/larasell/compare/v1.0.2...v1.1.0) (2026-08-29)


### Features

* add events for order and payment lifecycles ([1e1c19e](https://github.com/Larasell-dev/larasell/commit/1e1c19e2b916151582f5c68fd85c9d5938a24c11))
* add order cancelation ([462c69e](https://github.com/Larasell-dev/larasell/commit/462c69ec4f3435a92828a79116f012f487563fcb))
* add support for async payment methods ([#16](https://github.com/Larasell-dev/larasell/issues/16)) ([c77ed8d](https://github.com/Larasell-dev/larasell/commit/c77ed8db422c1b70863e26d4f8e85246b8dd60c0))
* add support for multiple payment methods ([cc5556b](https://github.com/Larasell-dev/larasell/commit/cc5556bb62341f818de03e9e9790abfab181894b))


### Bug Fixes

* enforce consistency across different databases ([2a68a82](https://github.com/Larasell-dev/larasell/commit/2a68a8283843945098636f84a85e4b281825fda4))

## [1.0.2](https://github.com/Larasell-dev/larasell/compare/v1.0.1...v1.0.2) (2026-08-27)


### Bug Fixes

* make fk and index names shorter ([1ab721f](https://github.com/Larasell-dev/larasell/commit/1ab721f655678290074f25f7a0186ce6661a8827))

## [1.0.1](https://github.com/Larasell-dev/larasell/compare/larasell-v1.0.0...larasell-v1.0.1) (2026-08-27)


### Bug Fixes

* update email ([7b285cf](https://github.com/Larasell-dev/larasell/commit/7b285cf5ac24b409bad59f8a9ce1af49d3a9ff25))

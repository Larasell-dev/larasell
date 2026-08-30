<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Admin\Models\AdminUser;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Models\Setting;
use Larasell\Larasell\Price;

it('generates and bulk updates variants in the admin panel', function () {
    $admin = AdminUser::query()->create(['name' => 'Admin', 'email' => 'variants@example.com', 'password' => Hash::make('password')]);
    $product = Product::query()->create(['slug' => 'admin-shirt', 'name' => 'Admin shirt', 'price' => Price::of(1000)]);
    $size = ProductAttribute::query()->create(['slug' => 'admin-size', 'name' => 'Size']);
    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $medium = $size->values()->create(['slug' => 'medium', 'name' => 'Medium', 'value' => 'medium']);
    $product->attributeValues()->attach([$small->id, $medium->id]);

    $this->actingAs($admin, 'larasell-admin')
        ->post(route('larasell.admin.products.variants.generate', $product), [
            'attribute_ids' => [$size->id],
        ])
        ->assertRedirect();

    $variants = $product->variants()->orderBy('id')->get();
    expect($variants)->toHaveCount(2);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.products.variants.update', $product), [
            'variants' => [
                [
                    'id' => $variants[0]->id,
                    'sku' => 'ADMIN-S',
                    'barcode' => '00001',
                    'price_amount' => 1200,
                    'stock' => 4,
                    'allow_backorders' => false,
                    'min_quantity' => 1,
                    'max_quantity' => 3,
                    'status' => 'visible',
                ],
                [
                    'id' => $variants[1]->id,
                    'sku' => 'ADMIN-M',
                    'barcode' => null,
                    'price_amount' => null,
                    'stock' => null,
                    'allow_backorders' => null,
                    'min_quantity' => null,
                    'max_quantity' => null,
                    'status' => 'hidden',
                ],
            ],
        ])
        ->assertRedirect();

    expect(ProductVariant::query()->find($variants[0]->id))
        ->sku->toBe('ADMIN-S')
        ->barcode->toBe('00001')
        ->stock->toBe(4)
        ->status->toBe(Visibility::Visible)
        ->and(ProductVariant::query()->find($variants[1]->id))
        ->sku->toBe('ADMIN-M')
        ->price->toBeNull()
        ->status->toBe(Visibility::Hidden);
});

function orderAttributes(array $overrides = []): array
{
    $address = [
        'country' => 'DE',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'street' => ['Main Street 1'],
        'city' => 'Berlin',
        'postcode' => '10115',
    ];

    return array_merge([
        'number' => 'ORDER-TEST',
        'currency' => Currency::EUR,
        'customer_email' => 'grace@example.com',
        'customer_name' => 'Grace Hopper',
        'billing_address' => $address,
        'shipping_address' => $address,
        'status' => OrderStatus::PendingPayment,
        'subtotal' => Price::of(1000),
        'total' => Price::of(1000),
    ], $overrides);
}

it('registers the admin login route when the admin provider is registered', function () {
    expect(route('larasell.admin.login'))->toContain('/admin/login');
});

it('redirects guest admin users to the larasell admin login route', function () {
    $this->get('/admin')->assertRedirect(route('larasell.admin.login'));
});

it('redirects guest admin users away from the products page', function () {
    $this->get('/admin/products')->assertRedirect(route('larasell.admin.login'));
});

it('redirects guest admin users away from the orders page', function () {
    $this->get('/admin/orders')->assertRedirect(route('larasell.admin.login'));
});

it('shows newest orders in the admin order index', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    Order::query()->create(orderAttributes(['number' => 'ORDER-0001']));
    $newest = Order::query()->create(orderAttributes([
        'number' => 'ORDER-0002',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'status' => OrderStatus::Paid,
        'total' => Price::of(1299),
    ]));

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.orders.index'))
        ->assertOk()
        ->assertJsonPath('component', 'Orders/Index')
        ->assertJsonPath('props.orders.0.id', $newest->getKey())
        ->assertJsonPath('props.orders.0.number', 'ORDER-0002')
        ->assertJsonPath('props.orders.0.customerEmail', 'ada@example.com')
        ->assertJsonPath('props.orders.0.status', 'paid')
        ->assertJsonPath('props.orders.0.total.amount', '1299')
        ->assertJsonPath('props.orders.0.currency', 'EUR')
        ->assertJsonPath('props.orders.0.url', route('larasell.admin.orders.show', $newest))
        ->assertJsonPath('props.pagination.currentPage', 1)
        ->assertJsonPath('props.pagination.total', 2)
        ->assertJsonPath('props.ordersUrl', route('larasell.admin.orders.index'));
});

it('shows an order with its snapshots and payments in the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $order = Order::query()->create(orderAttributes([
        'number' => 'ORDER-0042',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
    ]));
    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_name' => 'Desk lamp',
        'product_slug' => 'desk-lamp',
        'unit_price' => Price::of(500),
        'quantity' => 2,
        'total' => Price::of(1000),
    ]);
    Payment::query()->create([
        'order_id' => $order->getKey(),
        'method' => 'card',
        'provider' => 'stripe',
        'reference' => 'pi_123',
        'status' => PaymentStatus::Succeeded,
        'amount' => Price::of(1000),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.orders.show', $order))
        ->assertOk()
        ->assertJsonPath('component', 'Orders/Show')
        ->assertJsonPath('props.order.number', 'ORDER-0042')
        ->assertJsonPath('props.order.currency', 'EUR')
        ->assertJsonPath('props.order.customerName', 'Ada Lovelace')
        ->assertJsonPath('props.order.shippingAddress.city', 'Berlin')
        ->assertJsonPath('props.order.items.0.name', 'Desk lamp')
        ->assertJsonPath('props.order.items.0.quantity', 2)
        ->assertJsonPath('props.order.items.0.total.amount', '1000')
        ->assertJsonPath('props.order.payments.0.method', 'card')
        ->assertJsonPath('props.order.payments.0.provider', 'stripe')
        ->assertJsonPath('props.order.payments.0.reference', 'pi_123')
        ->assertJsonPath('props.order.payments.0.status', 'succeeded');
});

it('marks a pending order payment as paid from the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $order = Order::query()->create(orderAttributes());
    $payment = Payment::query()->create([
        'order_id' => $order->getKey(),
        'method' => 'cash',
        'provider' => 'offline',
        'status' => PaymentStatus::Pending,
        'amount' => Price::of(1000),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.orders.payments.paid', [$order, $payment]))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('does not mark a payment from another order as paid', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $order = Order::query()->create(orderAttributes(['number' => 'ORDER-0001']));
    $otherOrder = Order::query()->create(orderAttributes(['number' => 'ORDER-0002']));
    $payment = Payment::query()->create([
        'order_id' => $otherOrder->getKey(),
        'method' => 'cash',
        'provider' => 'offline',
        'status' => PaymentStatus::Pending,
        'amount' => Price::of(1000),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.orders.payments.paid', [$order, $payment]))
        ->assertNotFound();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->paid_at)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($otherOrder->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('redirects guest admin users away from the product attributes page', function () {
    $this->get('/admin/product-attributes')->assertRedirect(route('larasell.admin.login'));
});

it('redirects guest admin users away from the media page', function () {
    $this->get('/admin/media')->assertRedirect(route('larasell.admin.login'));
});

it('redirects guest admin users away from settings', function () {
    $this->get('/admin/settings')->assertRedirect(route('larasell.admin.login'));
    $this->get('/admin/settings/members')->assertRedirect(route('larasell.admin.login'));
});

it('shows settings and members to authenticated admin users', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.settings.index'))
        ->assertOk()
        ->assertJsonPath('component', 'Settings/Index')
        ->assertJsonPath('props.currenciesUrl', route('larasell.admin.settings.currencies.index'))
        ->assertJsonPath('props.membersUrl', route('larasell.admin.settings.members.index'));

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.settings.members.index'))
        ->assertOk()
        ->assertJsonPath('component', 'Settings/Members/Index')
        ->assertJsonPath('props.members.0.email', 'admin@example.com')
        ->assertJsonPath('props.members.0.url', route('larasell.admin.settings.members.show', $admin));

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.settings.members.show', $admin))
        ->assertOk()
        ->assertJsonPath('component', 'Settings/Members/Show')
        ->assertJsonPath('props.member.name', 'Larasell Admin')
        ->assertJsonPath('props.member.email', 'admin@example.com')
        ->assertJsonPath('props.member.updateUrl', route('larasell.admin.settings.members.update', $admin));
});

it('updates an admin member without requiring a new password', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $password = $admin->password;

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.settings.members.update', $admin), [
            'name' => 'Store Owner',
            'email' => 'admin@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect();

    $admin->refresh();

    expect($admin->name)->toBe('Store Owner')
        ->and($admin->email)->toBe('admin@example.com')
        ->and($admin->password)->toBe($password);
});

it('updates an admin member password', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.settings.members.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect();

    expect(Hash::check('new-password', $admin->refresh()->password))->toBeTrue();
});

it('deletes another admin member', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $member = AdminUser::query()->create([
        'name' => 'Store Manager',
        'email' => 'manager@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->delete(route('larasell.admin.settings.members.destroy', $member))
        ->assertRedirect(route('larasell.admin.settings.members.index'));

    expect(AdminUser::query()->whereKey($member->getKey())->exists())->toBeFalse()
        ->and(AdminUser::query()->whereKey($admin->getKey())->exists())->toBeTrue();
});

it('does not allow an admin member to delete themselves', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    AdminUser::query()->create([
        'name' => 'Store Manager',
        'email' => 'manager@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->delete(route('larasell.admin.settings.members.destroy', $admin))
        ->assertSessionHasErrors(['member' => 'You cannot delete your own admin account.']);

    expect(AdminUser::query()->whereKey($admin->getKey())->exists())->toBeTrue();
});

it('does not allow the last admin member to be deleted', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->delete(route('larasell.admin.settings.members.destroy', $admin))
        ->assertSessionHasErrors(['member' => 'At least one admin member must remain.']);

    expect(AdminUser::query()->count())->toBe(1);
});

it('creates a member from admin settings', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->post(route('larasell.admin.settings.members.store'), [
            'name' => 'Store Manager',
            'email' => 'manager@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])
        ->assertRedirect(route('larasell.admin.settings.members.index'));

    $member = AdminUser::query()->where('email', 'manager@example.com')->firstOrFail();

    expect($member->name)->toBe('Store Manager')
        ->and(Hash::check('secure-password', $member->password))->toBeTrue();
});

it('validates new admin members', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->from(route('larasell.admin.settings.members.create'))
        ->post(route('larasell.admin.settings.members.store'), [
            'name' => '',
            'email' => 'admin@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertRedirect(route('larasell.admin.settings.members.create'))
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('shows uploaded images in the admin media index', function () {
    Storage::fake('local');
    config()->set('larasell.images.disk', 'local');

    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    ProductImage::query()->create([
        'path' => 'products/desk-lamp/older.jpg',
        'alt' => 'Older image',
    ]);
    $image = ProductImage::query()->create([
        'path' => 'products/desk-lamp/hero.jpg',
        'alt' => 'Desk lamp',
        'meta' => ['original_name' => 'desk-lamp-hero.jpg'],
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.media.index'))
        ->assertOk()
        ->assertJsonPath('component', 'Media/Index')
        ->assertJsonPath('props.images.0.id', $image->id)
        ->assertJsonPath('props.images.0.alt', 'Desk lamp')
        ->assertJsonPath('props.images.0.name', 'desk-lamp-hero.jpg')
        ->assertJsonPath('props.images.0.url', $image->url())
        ->assertJsonPath('props.pagination.currentPage', 1)
        ->assertJsonPath('props.pagination.total', 2)
        ->assertJsonPath('props.mediaDeleteUrl', route('larasell.admin.media.destroy'))
        ->assertJsonPath('props.mediaUploadUrl', route('larasell.admin.media.uploads.store'))
        ->assertJsonPath('props.mediaUrl', route('larasell.admin.media.index'));
});

it('uploads an image to the media library with a generated file name', function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');
    config()->set('larasell.images.path', 'media');

    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->post(route('larasell.admin.media.uploads.store'), [
            'image' => UploadedFile::fake()->image('summer-campaign.jpg', 800, 800),
        ])
        ->assertRedirect();

    $image = ProductImage::query()->sole();

    Storage::disk('product-images')->assertExists($image->path);
    expect($image->path)->toStartWith('media/')
        ->not->toEndWith('summer-campaign.jpg')
        ->and($image->alt)->toBe('summer-campaign')
        ->and($image->meta['original_name'])->toBe('summer-campaign.jpg')
        ->and($image->meta['mime_type'])->toBe('image/jpeg');
});

it('deletes selected media images and their stored files', function () {
    Storage::fake('local');
    config()->set('larasell.images.disk', 'local');

    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $images = collect(['one.jpg', 'two.jpg'])->map(function (string $name) {
        Storage::disk('local')->put("products/$name", 'image');

        return ProductImage::query()->create(['path' => "products/$name"]);
    });

    $this->actingAs($admin, 'larasell-admin')
        ->delete(route('larasell.admin.media.destroy'), ['ids' => $images->pluck('id')->all()])
        ->assertRedirect();

    expect(ProductImage::query()->whereKey($images->pluck('id'))->exists())->toBeFalse();
    Storage::disk('local')->assertMissing(['products/one.jpg', 'products/two.jpg']);
});

it('redirects authenticated admin users to the larasell admin home route from login', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->get('/admin/login')
        ->assertRedirect(route('larasell.admin.home'));
});

it('creates an admin user from the console command', function () {
    $this->artisan('admin:create-user', [
        '--name' => 'Larasell Admin',
        '--email' => 'admin@example.com',
        '--password' => 'password',
    ])->assertSuccessful();

    $admin = AdminUser::query()->where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Larasell Admin')
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});

it('shows products in the admin product index', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
        'stock' => 12,
        'status' => Visibility::Visible,
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get('/admin/products')
        ->assertOk()
        ->assertJsonPath('component', 'Products/Index')
        ->assertJsonPath('props.products.0.name', 'Desk lamp')
        ->assertJsonPath('props.products.0.price.amount', '4999')
        ->assertJsonMissingPath('props.products.0.price.currency')
        ->assertJsonPath('props.products.0.stock', 12)
        ->assertJsonPath('props.products.0.status', 'visible')
        ->assertJsonPath('props.products.0.url', route('larasell.admin.products.show', $product))
        ->assertJsonPath('props.products.0.deleteUrl', route('larasell.admin.products.destroy', $product))
        ->assertJsonPath('props.pagination.currentPage', 1)
        ->assertJsonPath('props.pagination.total', 1)
        ->assertJsonPath('props.productCreateUrl', route('larasell.admin.products.create'))
        ->assertJsonPath('props.productsUrl', route('larasell.admin.products.index'));
});

it('deletes a product', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->delete(route('larasell.admin.products.destroy', $product))
        ->assertRedirect(route('larasell.admin.products.index'));

    expect(Product::query()->whereKey($product->getKey())->exists())->toBeFalse();
});

it('shows the product create page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $category = Category::query()->create([
        'name' => 'Lighting',
        'slug' => 'lighting',
        'status' => Visibility::Visible,
    ]);
    $option = ProductAttribute::query()->create(['name' => 'Size', 'slug' => 'size', 'type' => ProductAttributeType::Text]);
    $large = $option->values()->create(['name' => 'Large', 'slug' => 'large', 'value' => 'large']);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.products.create'))
        ->assertOk()
        ->assertJsonPath('component', 'Products/Create')
        ->assertJsonPath('props.categories.0.label', 'Lighting')
        ->assertJsonPath('props.categories.0.value', (string) $category->id)
        ->assertJsonPath('props.productAttributes.0.name', 'Size')
        ->assertJsonPath('props.productAttributes.0.values.0.name', 'Large')
        ->assertJsonPath('props.productAttributes.0.values.0.id', (string) $large->id)
        ->assertJsonPath('props.productStoreUrl', route('larasell.admin.products.store'))
        ->assertJsonPath('props.productsUrl', route('larasell.admin.products.index'));
});

it('creates a product in the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(1000),
    ]);
    $category = Category::query()->create([
        'name' => 'Lighting',
        'slug' => 'lighting',
        'status' => Visibility::Visible,
    ]);
    $option = ProductAttribute::query()->create(['name' => 'Size', 'slug' => 'size', 'type' => ProductAttributeType::Text]);
    $large = $option->values()->create(['name' => 'Large', 'slug' => 'large', 'value' => 'large']);
    Setting::query()->where('key', 'currencies')->update([
        'value' => ['enabled' => ['USD', 'EUR']],
    ]);

    $response = $this->actingAs($admin, 'larasell-admin')
        ->post(route('larasell.admin.products.store'), [
            'name' => 'Desk lamp',
            'description' => 'A focused task light.',
            'stock' => 12,
            'min_quantity' => 2,
            'max_quantity' => 6,
            'allow_backorders' => false,
            'status' => 'hidden',
            'price_amount' => 4999,
            'category_ids' => [(string) $category->id],
            'attribute_value_ids' => [(string) $large->id],
        ]);

    $product = Product::query()->where('slug->en', 'desk-lamp-2')->sole();

    $response->assertRedirect(route('larasell.admin.products.show', $product));
    expect($product->name->get())->toBe('Desk lamp');
    expect($product)
        ->description->get()->toBe('A focused task light.')
        ->stock->toBe(12)
        ->min_quantity->toBe(2)
        ->max_quantity->toBe(6)
        ->allow_backorders->toBeFalse()
        ->status->toBe(Visibility::Hidden)
        ->price->toEqual(Price::of(4999));
    expect($product->categories()->pluck('larasell_categories.id')->all())->toBe([$category->id]);
    expect($product->attributeValues()->pluck('larasell_product_attribute_values.id')->all())->toBe([$large->id]);
});

it('validates product creation', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->from(route('larasell.admin.products.create'))
        ->post(route('larasell.admin.products.store'), [
            'name' => '',
            'stock' => -1,
            'min_quantity' => 5,
            'max_quantity' => 2,
            'allow_backorders' => false,
            'status' => 'unknown',
            'price_amount' => -1,
            'category_ids' => ['999999'],
            'attribute_value_ids' => ['999999'],
        ])
        ->assertRedirect(route('larasell.admin.products.create'))
        ->assertSessionHasErrors(['name', 'stock', 'min_quantity', 'max_quantity', 'status', 'price_amount', 'category_ids.0', 'attribute_value_ids.0']);

    expect(Product::query()->count())->toBe(0);
});

it('shows product attributes in the admin product attribute index', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $booleanOption = ProductAttribute::query()->create([
        'slug' => 'featured',
        'name' => 'Featured',
        'type' => ProductAttributeType::Boolean,
    ]);
    $option = ProductAttribute::query()->create([
        'slug' => 'size',
        'name' => 'Size',
        'type' => 'text',
    ]);
    $option->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'S',
        'position' => 0,
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get('/admin/product-attributes')
        ->assertOk()
        ->assertJsonPath('component', 'ProductAttributes/Index')
        ->assertJsonPath('props.productAttributes.0.name', 'Size')
        ->assertJsonPath('props.productAttributes.0.type', 'text')
        ->assertJsonPath('props.productAttributes.0.url', route('larasell.admin.product-attributes.show', $option))
        ->assertJsonPath('props.productAttributes.0.deleteUrl', route('larasell.admin.product-attributes.destroy', $option))
        ->assertJsonPath('props.productAttributes.0.valuesCount', 1)
        ->assertJsonPath('props.productAttributes.1.id', $booleanOption->id)
        ->assertJsonPath('props.productAttributes.1.type', 'boolean')
        ->assertJsonPath('props.productAttributes.1.valuesCount', 0)
        ->assertJsonPath('props.pagination.total', 2)
        ->assertJsonPath('props.productAttributeCreateUrl', route('larasell.admin.product-attributes.create'))
        ->assertJsonPath('props.productAttributesUrl', route('larasell.admin.product-attributes.index'));
});

it('deletes a product attribute', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductAttribute::query()->create([
        'slug' => 'size',
        'name' => 'Size',
        'type' => 'text',
    ]);
    $value = $option->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'S',
        'position' => 0,
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->delete(route('larasell.admin.product-attributes.destroy', $option))
        ->assertRedirect(route('larasell.admin.product-attributes.index'));

    $this->assertDatabaseMissing('larasell_product_attributes', ['id' => $option->id]);
    $this->assertDatabaseMissing('larasell_product_attribute_values', ['id' => $value->id]);
});

it('shows the product attribute create page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get('/admin/product-attributes/create')
        ->assertOk()
        ->assertJsonPath('component', 'ProductAttributes/Create')
        ->assertJsonPath('props.productAttributeStoreUrl', route('larasell.admin.product-attributes.store'))
        ->assertJsonPath('props.productAttributesUrl', route('larasell.admin.product-attributes.index'));
});

it('stores a product attribute and redirects to its show page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($admin, 'larasell-admin')->post('/admin/product-attributes', [
        'name' => 'Size',
        'type' => 'text',
        'values' => [
            ['value' => 'Small'],
            ['value' => 'Large'],
            ['value' => ''],
        ],
    ]);

    $option = ProductAttribute::query()->where('slug', 'size')->firstOrFail();

    $response->assertRedirect(route('larasell.admin.product-attributes.show', $option));
    expect($option->name)->toBe('Size')
        ->and($option->type->value)->toBe('text')
        ->and($option->values()->orderBy('position')->get()->pluck('value')->all())->toBe(['Small', 'Large']);
});

it('requires a name when storing a product attribute', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')->post('/admin/product-attributes', [
        'name' => '',
        'type' => 'text',
        'values' => [['value' => 'Small']],
    ])->assertSessionHasErrors('name');

    expect(ProductAttribute::query()->count())->toBe(0);
});

it('shows the product attribute show page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductAttribute::query()->create([
        'slug' => 'size',
        'name' => 'Size',
        'type' => 'text',
    ]);
    $value = $option->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'Small',
        'position' => 0,
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.product-attributes.show', $option))
        ->assertOk()
        ->assertJsonPath('component', 'ProductAttributes/Show')
        ->assertJsonPath('props.productAttribute.name', 'Size')
        ->assertJsonPath('props.productAttribute.type', 'text')
        ->assertJsonPath('props.productAttribute.values.0.id', $value->getKey())
        ->assertJsonPath('props.productAttribute.values.0.value', 'Small')
        ->assertJsonPath('props.productAttribute.updateUrl', route('larasell.admin.product-attributes.update', $option))
        ->assertJsonPath('props.productAttributesUrl', route('larasell.admin.product-attributes.index'));
});

it('updates a product attribute and synchronizes its values', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductAttribute::query()->create([
        'slug' => 'size',
        'name' => 'Size',
        'type' => 'text',
    ]);
    $small = $option->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'Small', 'position' => 0]);
    $large = $option->values()->create(['slug' => 'large', 'name' => 'Large', 'value' => 'Large', 'position' => 1]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.product-attributes.update', $option), [
            'name' => 'Clothing size',
            'type' => 'text',
            'values' => [
                ['id' => $large->getKey(), 'value' => 'Extra large'],
                ['value' => 'Medium'],
            ],
        ])
        ->assertRedirect();

    expect($option->refresh()->name)->toBe('Clothing size')
        ->and($option->values()->orderBy('position')->pluck('value')->all())->toBe(['Extra large', 'Medium']);
    $this->assertDatabaseMissing('larasell_product_attribute_values', ['id' => $small->getKey()]);
});

it('removes product attribute values when changing to boolean', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductAttribute::query()->create(['slug' => 'size', 'name' => 'Size', 'type' => 'text']);
    $option->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'Small', 'position' => 0]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.product-attributes.update', $option), [
            'name' => 'Available',
            'type' => 'boolean',
            'values' => [],
        ])
        ->assertRedirect();

    expect($option->refresh()->type)->toBe(ProductAttributeType::Boolean)
        ->and($option->values()->count())->toBe(2)
        ->and($option->values()->orderBy('position')->pluck('name')->all())->toBe(['Yes', 'No'])
        ->and($option->values()->orderBy('position')->pluck('value')->all())->toBe([true, false]);
});

it('paginates the admin product index from the page query parameter', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    foreach (range(1, 26) as $number) {
        Product::query()->create([
            'slug' => "product-$number",
            'name' => "Product $number",
            'price' => Price::of(1000),
            'status' => Visibility::Visible,
        ]);
    }

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get('/admin/products?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'props.products')
        ->assertJsonPath('props.products.0.name', 'Product 1')
        ->assertJsonPath('props.pagination.currentPage', 2)
        ->assertJsonPath('props.pagination.previousUrl', route('larasell.admin.products.index').'?page=1')
        ->assertJsonPath('props.pagination.nextUrl', null)
        ->assertJsonPath('props.pagination.total', 26);
});

it('defers product images on the admin product index', function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');

    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
        'status' => Visibility::Visible,
    ]);
    $image = ProductImage::query()->create([
        'path' => 'products/desk-lamp.jpg',
        'alt' => 'A brass desk lamp',
    ]);
    $product->images()->attach($image, ['position' => 0]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get('/admin/products')
        ->assertOk()
        ->assertJsonMissingPath('props.productImages')
        ->assertJsonPath('deferredProps.default.0', 'productImages');

    $this->actingAs($admin, 'larasell-admin')
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Products/Index',
            'X-Inertia-Partial-Data' => 'productImages',
        ])
        ->get('/admin/products')
        ->assertOk()
        ->assertJsonPath("props.productImages.{$product->id}.url", $image->url())
        ->assertJsonPath("props.productImages.{$product->id}.alt", 'A brass desk lamp');
});

it('shows a product in the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'description' => 'A focused task light.',
        'price' => Price::of(4999),
        'stock' => 12,
        'min_quantity' => 2,
        'max_quantity' => 6,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $category = Category::query()->create([
        'name' => 'Lighting',
        'slug' => 'lighting',
        'status' => Visibility::Visible,
    ]);
    $childCategory = Category::query()->create([
        'name' => 'Desk lamps',
        'slug' => 'desk-lamps',
        'parent_id' => $category->id,
        'status' => Visibility::Visible,
    ]);
    $product->categories()->attach($category);
    $option = ProductAttribute::query()->create(['name' => 'Color', 'slug' => 'color', 'type' => ProductAttributeType::Text]);
    $black = $option->values()->create(['name' => 'Black', 'slug' => 'black', 'value' => 'black']);
    $product->attributeValues()->attach($black);
    $booleanOption = ProductAttribute::query()->create(['name' => 'Featured', 'slug' => 'featured', 'type' => ProductAttributeType::Boolean]);
    $yes = $booleanOption->values()->create(['name' => 'Yes', 'slug' => '__boolean_true', 'value' => true, 'position' => 0]);
    $no = $booleanOption->values()->create(['name' => 'No', 'slug' => '__boolean_false', 'value' => false, 'position' => 1]);

    Route::bind('product', fn (string $value): Product => Product::query()
        ->where('slug', $value)
        ->firstOrFail());

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.products.show', $product))
        ->assertOk()
        ->assertJsonPath('component', 'Products/Show')
        ->assertJsonPath('props.product.id', $product->id)
        ->assertJsonPath('props.product.name', 'Desk lamp')
        ->assertJsonPath('props.product.slug', 'desk-lamp')
        ->assertJsonPath('props.product.description', 'A focused task light.')
        ->assertJsonPath('props.product.stock', 12)
        ->assertJsonPath('props.product.minQuantity', 2)
        ->assertJsonPath('props.product.maxQuantity', 6)
        ->assertJsonPath('props.product.allowBackorders', false)
        ->assertJsonPath('props.product.status', 'visible')
        ->assertJsonPath('props.product.price.amount', '4999')
        ->assertJsonMissingPath('props.product.price.currency')
        ->assertJsonPath('props.product.categoryIds.0', (string) $category->id)
        ->assertJsonPath('props.product.attributeValueIds.0', (string) $black->id)
        ->assertJsonPath('props.categories.0.label', 'Lighting')
        ->assertJsonPath('props.categories.0.children.0.label', 'Desk lamps')
        ->assertJsonPath('props.categories.0.children.0.value', (string) $childCategory->id)
        ->assertJsonPath('props.categories.0.children.0.children', [])
        ->assertJsonPath('props.productAttributes.0.name', 'Color')
        ->assertJsonPath('props.productAttributes.0.type', 'text')
        ->assertJsonPath('props.productAttributes.0.values.0.name', 'Black')
        ->assertJsonPath('props.productAttributes.1.id', (string) $booleanOption->id)
        ->assertJsonPath('props.productAttributes.1.name', 'Featured')
        ->assertJsonPath('props.productAttributes.1.type', 'boolean')
        ->assertJsonPath('props.productAttributes.1.values.0.id', (string) $yes->id)
        ->assertJsonPath('props.productAttributes.1.values.0.name', 'Yes')
        ->assertJsonPath('props.productAttributes.1.values.0.value', true)
        ->assertJsonPath('props.productAttributes.1.values.1.id', (string) $no->id)
        ->assertJsonPath('props.productAttributes.1.values.1.name', 'No')
        ->assertJsonPath('props.productAttributes.1.values.1.value', false)
        ->assertJsonPath('props.product.updateUrl', route('larasell.admin.products.update', $product))
        ->assertJsonPath('props.product.imageUploadUrl', route('larasell.admin.products.images.store', $product))
        ->assertJsonPath('props.product.generalUpdateUrl', route('larasell.admin.products.general.update', $product))
        ->assertJsonPath('props.product.stockUpdateUrl', route('larasell.admin.products.stock.update', $product))
        ->assertJsonPath('props.product.variantGenerateUrl', route('larasell.admin.products.variants.generate', $product))
        ->assertJsonPath('props.product.variantUpdateUrl', route('larasell.admin.products.variants.update', $product))
        ->assertJsonPath('props.variantDimensionIds', [])
        ->assertJsonPath('props.variants', [])
        ->assertJsonMissingPath('props.images')
        ->assertJsonPath('deferredProps.default.0', 'images')
        ->assertJsonPath('props.productsUrl', route('larasell.admin.products.index'));
});

it('uploads an image without attaching it to a product', function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');

    $admin = AdminUser::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('password')]);
    $product = Product::query()->create(['slug' => 'desk-lamp', 'name' => 'Desk lamp', 'price' => Price::of(4999)]);
    $existingImage = ProductImage::query()->create(['path' => 'products/existing.jpg']);
    $product->images()->attach($existingImage, ['position' => 0]);

    $this->actingAs($admin, 'larasell-admin')
        ->post(route('larasell.admin.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('side-view.jpg', 800, 800),
        ])
        ->assertCreated()
        ->assertJsonPath('image.alt', 'side-view');

    $uploadedImage = ProductImage::query()->whereKeyNot($existingImage->id)->sole();

    Storage::disk('product-images')->assertExists($uploadedImage->path);
    expect($uploadedImage->alt)->toBe('side-view')
        ->and($uploadedImage->meta['original_name'])->toBe('side-view.jpg')
        ->and($uploadedImage->meta['pending_product_id'])->toBe((string) $product->id)
        ->and($product->images()->whereKey($uploadedImage->id)->exists())->toBeFalse();
});

it('defers ordered images on the admin product page', function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');

    $admin = AdminUser::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('password')]);
    $product = Product::query()->create(['slug' => 'desk-lamp', 'name' => 'Desk lamp', 'price' => Price::of(4999)]);
    $first = ProductImage::query()->create(['path' => 'products/first.jpg', 'alt' => 'First']);
    $second = ProductImage::query()->create(['path' => 'products/second.jpg', 'alt' => 'Second']);
    $product->images()->attach($first, ['position' => 1]);
    $product->images()->attach($second, ['position' => 0]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Products/Show', 'X-Inertia-Partial-Data' => 'images'])
        ->get(route('larasell.admin.products.show', $product))
        ->assertOk()
        ->assertJsonPath('props.images.0.id', $second->id)
        ->assertJsonPath('props.images.0.alt', 'Second')
        ->assertJsonPath('props.images.1.id', $first->id);
});

it('updates all product settings in the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);
    $oldCategory = Category::query()->create(['name' => 'Old', 'slug' => 'old', 'status' => Visibility::Visible]);
    $newCategory = Category::query()->create(['name' => 'Lighting', 'slug' => 'lighting', 'status' => Visibility::Visible]);
    $product->categories()->attach($oldCategory);
    $option = ProductAttribute::query()->create(['name' => 'Color', 'slug' => 'color', 'type' => ProductAttributeType::Text]);
    $black = $option->values()->create(['name' => 'Black', 'slug' => 'black', 'value' => 'black']);
    $white = $option->values()->create(['name' => 'White', 'slug' => 'white', 'value' => 'white']);
    $product->attributeValues()->attach($black);
    $firstImage = ProductImage::query()->create(['path' => 'products/first.jpg']);
    $secondImage = ProductImage::query()->create(['path' => 'products/second.jpg']);
    $uploadedImage = ProductImage::query()->create(['path' => 'products/uploaded.jpg', 'meta' => ['pending_product_id' => (string) $product->id]]);
    $product->images()->attach($firstImage, ['position' => 0]);
    $product->images()->attach($secondImage, ['position' => 1]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.products.update', $product), [
            'name' => 'Reading lamp',
            'slug' => 'reading-lamp',
            'description' => 'Warm, adjustable light.',
            'stock' => 20,
            'min_quantity' => 2,
            'max_quantity' => 8,
            'allow_backorders' => false,
            'status' => 'hidden',
            'price_amount' => 5995,
            'image_order' => [$secondImage->id, $uploadedImage->id, $firstImage->id],
            'new_image_ids' => [$uploadedImage->id],
            'category_ids' => [(string) $newCategory->id],
            'attribute_value_ids' => [(string) $white->id],
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->name->get())->toBe('Reading lamp');
    expect($product)
        ->slug->get()->toBe('reading-lamp')
        ->description->get()->toBe('Warm, adjustable light.')
        ->stock->toBe(20)
        ->min_quantity->toBe(2)
        ->max_quantity->toBe(8)
        ->allow_backorders->toBeFalse()
        ->status->toBe(Visibility::Hidden)
        ->price->toEqual(Price::of(5995));

    expect($product->images()->pluck('larasell_product_images.id')->all())->toBe([$secondImage->id, $uploadedImage->id, $firstImage->id])
        ->and($product->categories()->pluck('larasell_categories.id')->all())->toBe([$newCategory->id])
        ->and($product->attributeValues()->pluck('larasell_product_attribute_values.id')->all())->toBe([$white->id])
        ->and($uploadedImage->refresh()->meta)->not->toHaveKey('pending_product_id');
});

it('updates product general information in the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.products.general.update', $product), [
            'name' => 'Reading lamp',
            'slug' => 'reading-lamp',
            'description' => 'Warm, adjustable light.',
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->name->get())->toBe('Reading lamp');
    expect($product)
        ->slug->get()->toBe('reading-lamp')
        ->description->get()->toBe('Warm, adjustable light.');
});

it('updates product stock settings in the admin panel', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.products.stock.update', $product), [
            'stock' => 20,
            'min_quantity' => 2,
            'max_quantity' => 8,
            'allow_backorders' => false,
        ])
        ->assertRedirect();

    expect($product->refresh())
        ->stock->toBe(20)
        ->min_quantity->toBe(2)
        ->max_quantity->toBe(8)
        ->allow_backorders->toBeFalse();
});

it('validates product stock quantity boundaries', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->from(route('larasell.admin.products.show', $product))
        ->patch(route('larasell.admin.products.stock.update', $product), [
            'stock' => -1,
            'min_quantity' => 5,
            'max_quantity' => 2,
            'allow_backorders' => true,
        ])
        ->assertRedirect(route('larasell.admin.products.show', $product))
        ->assertSessionHasErrors(['stock', 'min_quantity', 'max_quantity']);
});

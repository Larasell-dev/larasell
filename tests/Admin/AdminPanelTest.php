<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Admin\Models\AdminUser;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Price;

it('registers the admin login route when the admin provider is registered', function () {
    expect(route('larasell.admin.login'))->toContain('/admin/login');
});

it('redirects guest admin users to the larasell admin login route', function () {
    $this->get('/admin')->assertRedirect(route('larasell.admin.login'));
});

it('redirects guest admin users away from the products page', function () {
    $this->get('/admin/products')->assertRedirect(route('larasell.admin.login'));
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
        'price' => Price::of(4999, 'EUR'),
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
        ->assertJsonPath('props.products.0.price.currency', 'EUR')
        ->assertJsonPath('props.products.0.stock', 12)
        ->assertJsonPath('props.products.0.status', 'visible')
        ->assertJsonPath('props.products.0.url', route('larasell.admin.products.show', $product))
        ->assertJsonPath('props.pagination.currentPage', 1)
        ->assertJsonPath('props.pagination.total', 1)
        ->assertJsonPath('props.productsUrl', route('larasell.admin.products.index'));
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
            'price' => Price::of(1000, 'EUR'),
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
        'price' => Price::of(4999, 'EUR'),
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
        'price' => Price::of(4999, 'EUR'),
        'status' => Visibility::Visible,
    ]);

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
        ->assertJsonPath('props.productsUrl', route('larasell.admin.products.index'));
});

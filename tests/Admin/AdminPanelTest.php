<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Admin\Models\AdminUser;
use Larasell\Larasell\Enums\ProductOptionType;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Models\ProductOption;
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

it('redirects guest admin users away from the product options page', function () {
    $this->get('/admin/product-options')->assertRedirect(route('larasell.admin.login'));
});

it('redirects guest admin users away from the media page', function () {
    $this->get('/admin/media')->assertRedirect(route('larasell.admin.login'));
});

it('shows uploaded images in the admin media index', function () {
    Storage::fake('local');
    config()->set('larasell.images.disk', 'local');

    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
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
        ->assertJsonPath('props.pagination.total', 1)
        ->assertJsonPath('props.mediaDeleteUrl', route('larasell.admin.media.destroy'))
        ->assertJsonPath('props.mediaUrl', route('larasell.admin.media.index'));
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
        'price' => Price::of(4999, 'EUR'),
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

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.products.create'))
        ->assertOk()
        ->assertJsonPath('component', 'Products/Create')
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
        'price' => Price::of(1000, 'EUR'),
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
            'price_amount' => 49.99,
            'price_currency' => 'EUR',
        ]);

    $product = Product::query()->where('slug', 'desk-lamp-2')->sole();

    $response->assertRedirect(route('larasell.admin.products.show', $product));
    expect($product)
        ->name->toBe('Desk lamp')
        ->description->toBe('A focused task light.')
        ->stock->toBe(12)
        ->min_quantity->toBe(2)
        ->max_quantity->toBe(6)
        ->allow_backorders->toBeFalse()
        ->status->toBe(Visibility::Hidden)
        ->price->toEqual(Price::of(4999, 'EUR'));
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
            'price_currency' => 'BTC',
        ])
        ->assertRedirect(route('larasell.admin.products.create'))
        ->assertSessionHasErrors(['name', 'stock', 'min_quantity', 'max_quantity', 'status', 'price_amount', 'price_currency']);

    expect(Product::query()->count())->toBe(0);
});

it('shows product options in the admin product option index', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $option = ProductOption::query()->create([
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
        ->get('/admin/product-options')
        ->assertOk()
        ->assertJsonPath('component', 'ProductOptions/Index')
        ->assertJsonPath('props.productOptions.0.name', 'Size')
        ->assertJsonPath('props.productOptions.0.type', 'text')
        ->assertJsonPath('props.productOptions.0.url', route('larasell.admin.product-options.show', $option))
        ->assertJsonPath('props.productOptions.0.deleteUrl', route('larasell.admin.product-options.destroy', $option))
        ->assertJsonPath('props.productOptions.0.valuesCount', 1)
        ->assertJsonPath('props.pagination.total', 1)
        ->assertJsonPath('props.productOptionCreateUrl', route('larasell.admin.product-options.create'))
        ->assertJsonPath('props.productOptionsUrl', route('larasell.admin.product-options.index'));
});

it('deletes a product option', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductOption::query()->create([
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
        ->delete(route('larasell.admin.product-options.destroy', $option))
        ->assertRedirect(route('larasell.admin.product-options.index'));

    $this->assertDatabaseMissing('larasell_product_options', ['id' => $option->id]);
    $this->assertDatabaseMissing('larasell_product_option_values', ['id' => $value->id]);
});

it('shows the product option create page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get('/admin/product-options/create')
        ->assertOk()
        ->assertJsonPath('component', 'ProductOptions/Create')
        ->assertJsonPath('props.productOptionStoreUrl', route('larasell.admin.product-options.store'))
        ->assertJsonPath('props.productOptionsUrl', route('larasell.admin.product-options.index'));
});

it('stores a product option and redirects to its show page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($admin, 'larasell-admin')->post('/admin/product-options', [
        'name' => 'Size',
        'type' => 'text',
        'options' => [
            ['value' => 'Small'],
            ['value' => 'Large'],
            ['value' => ''],
        ],
    ]);

    $option = ProductOption::query()->where('slug', 'size')->firstOrFail();

    $response->assertRedirect(route('larasell.admin.product-options.show', $option));
    expect($option->name)->toBe('Size')
        ->and($option->type->value)->toBe('text')
        ->and($option->values()->orderBy('position')->get()->pluck('value')->all())->toBe(['Small', 'Large']);
});

it('requires a name when storing a product option', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin, 'larasell-admin')->post('/admin/product-options', [
        'name' => '',
        'type' => 'text',
        'options' => [['value' => 'Small']],
    ])->assertSessionHasErrors('name');

    expect(ProductOption::query()->count())->toBe(0);
});

it('shows the product option show page', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductOption::query()->create([
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
        ->get(route('larasell.admin.product-options.show', $option))
        ->assertOk()
        ->assertJsonPath('component', 'ProductOptions/Show')
        ->assertJsonPath('props.productOption.name', 'Size')
        ->assertJsonPath('props.productOption.type', 'text')
        ->assertJsonPath('props.productOption.values.0.id', $value->getKey())
        ->assertJsonPath('props.productOption.values.0.value', 'Small')
        ->assertJsonPath('props.productOption.updateUrl', route('larasell.admin.product-options.update', $option))
        ->assertJsonPath('props.productOptionsUrl', route('larasell.admin.product-options.index'));
});

it('updates a product option and synchronizes its values', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductOption::query()->create([
        'slug' => 'size',
        'name' => 'Size',
        'type' => 'text',
    ]);
    $small = $option->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'Small', 'position' => 0]);
    $large = $option->values()->create(['slug' => 'large', 'name' => 'Large', 'value' => 'Large', 'position' => 1]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.product-options.update', $option), [
            'name' => 'Clothing size',
            'type' => 'text',
            'options' => [
                ['id' => $large->getKey(), 'value' => 'Extra large'],
                ['value' => 'Medium'],
            ],
        ])
        ->assertRedirect();

    expect($option->refresh()->name)->toBe('Clothing size')
        ->and($option->values()->orderBy('position')->pluck('value')->all())->toBe(['Extra large', 'Medium']);
    $this->assertDatabaseMissing('larasell_product_option_values', ['id' => $small->getKey()]);
});

it('removes product option values when changing to boolean', function () {
    $admin = AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);
    $option = ProductOption::query()->create(['slug' => 'size', 'name' => 'Size', 'type' => 'text']);
    $option->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'Small', 'position' => 0]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.product-options.update', $option), [
            'name' => 'Available',
            'type' => 'boolean',
            'options' => [],
        ])
        ->assertRedirect();

    expect($option->refresh()->type)->toBe(ProductOptionType::Boolean)
        ->and($option->values()->count())->toBe(0);
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
        'description' => 'A focused task light.',
        'price' => Price::of(4999, 'EUR'),
        'stock' => 12,
        'min_quantity' => 2,
        'max_quantity' => 6,
        'allow_backorders' => false,
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
        ->assertJsonPath('props.product.slug', 'desk-lamp')
        ->assertJsonPath('props.product.description', 'A focused task light.')
        ->assertJsonPath('props.product.stock', 12)
        ->assertJsonPath('props.product.minQuantity', 2)
        ->assertJsonPath('props.product.maxQuantity', 6)
        ->assertJsonPath('props.product.allowBackorders', false)
        ->assertJsonPath('props.product.status', 'visible')
        ->assertJsonPath('props.product.price.amount', '4999')
        ->assertJsonPath('props.product.price.currency', 'EUR')
        ->assertJsonPath('props.product.updateUrl', route('larasell.admin.products.update', $product))
        ->assertJsonPath('props.product.imageUploadUrl', route('larasell.admin.products.images.store', $product))
        ->assertJsonPath('props.product.generalUpdateUrl', route('larasell.admin.products.general.update', $product))
        ->assertJsonPath('props.product.stockUpdateUrl', route('larasell.admin.products.stock.update', $product))
        ->assertJsonMissingPath('props.images')
        ->assertJsonPath('deferredProps.default.0', 'images')
        ->assertJsonPath('props.productsUrl', route('larasell.admin.products.index'));
});

it('uploads an image without attaching it to a product', function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');

    $admin = AdminUser::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('password')]);
    $product = Product::query()->create(['slug' => 'desk-lamp', 'name' => 'Desk lamp', 'price' => Price::of(4999, 'EUR')]);
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
    $product = Product::query()->create(['slug' => 'desk-lamp', 'name' => 'Desk lamp', 'price' => Price::of(4999, 'EUR')]);
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
        'price' => Price::of(4999, 'EUR'),
    ]);
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
            'price_amount' => 59.95,
            'price_currency' => 'USD',
            'image_order' => [$secondImage->id, $uploadedImage->id, $firstImage->id],
            'new_image_ids' => [$uploadedImage->id],
        ])
        ->assertRedirect();

    expect($product->refresh())
        ->name->toBe('Reading lamp')
        ->slug->toBe('reading-lamp')
        ->description->toBe('Warm, adjustable light.')
        ->stock->toBe(20)
        ->min_quantity->toBe(2)
        ->max_quantity->toBe(8)
        ->allow_backorders->toBeFalse()
        ->status->toBe(Visibility::Hidden)
        ->price->toEqual(Price::of(5995, 'USD'));

    expect($product->images()->pluck('larasell_product_images.id')->all())->toBe([$secondImage->id, $uploadedImage->id, $firstImage->id])
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
        'price' => Price::of(4999, 'EUR'),
    ]);

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.products.general.update', $product), [
            'name' => 'Reading lamp',
            'slug' => 'reading-lamp',
            'description' => 'Warm, adjustable light.',
        ])
        ->assertRedirect();

    expect($product->refresh())
        ->name->toBe('Reading lamp')
        ->slug->toBe('reading-lamp')
        ->description->toBe('Warm, adjustable light.');
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
        'price' => Price::of(4999, 'EUR'),
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
        'price' => Price::of(4999, 'EUR'),
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

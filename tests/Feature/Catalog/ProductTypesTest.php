<?php

use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\CoreProductType;
use Larasell\Larasell\Catalog\Exceptions\InvalidProductType;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\ProductCapabilities;
use Larasell\Larasell\Catalog\ProductType;
use Larasell\Larasell\Catalog\ProductTypes;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Inventory\InventoryCapabilities;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingCapabilities;

function registerDownloadType(): void
{
    app(ProductCapabilities::class)->register('download');
    app(ProductTypes::class)->register(ProductType::make('download')->label('Download')->capabilities(['download']));
}

it('registers only the physical type, with stock, shipping and dimensions', function () {
    $types = app(ProductTypes::class);

    expect(array_map(fn (ProductType $type) => $type->key(), $types->all()))->toBe(['physical'])
        ->and($types->get(CoreProductType::Physical)->capabilities())->toBe([
            InventoryCapabilities::STOCK,
            ShippingCapabilities::SHIPPING,
            ShippingCapabilities::DIMENSIONS,
        ]);
});

it('makes products physical unless told otherwise', function () {
    $product = Product::create(['name' => 'Tee', 'price' => Price::of(1000)]);

    expect($product->fresh()->type)->toBe('physical')
        ->and($product->has('shipping'))->toBeTrue()
        ->and($product->defaultVariant()->type)->toBeNull()
        ->and($product->defaultVariant()->effectiveType()->key())->toBe('physical');
});

it('creates products of a registered custom type', function () {
    registerDownloadType();

    $product = app(CreateProduct::class)->handle(new CreateProductPayload(
        ['name' => 'Ebook', 'price' => Price::of(900)],
        type: 'download',
    ), origin());

    expect($product->type)->toBe('download')
        ->and($product->productType()->label())->toBe('Download')
        ->and($product->has('download'))->toBeTrue()
        ->and($product->has('shipping'))->toBeFalse()
        ->and(Product::query()->ofType('download')->pluck('id')->all())->toBe([$product->id])
        ->and(Product::query()->withCapability('shipping')->count())->toBe(0);
});

it('rejects products of an unregistered type', function () {
    expect(fn () => Product::create(['name' => 'X', 'price' => Price::of(1), 'type' => 'unknown']))
        ->toThrow(InvalidProductType::class);
});

it('does not let a product or variant change its type', function () {
    registerDownloadType();
    $product = Product::create(['name' => 'Tee', 'price' => Price::of(1000)]);

    expect(fn () => $product->update(['type' => 'download']))->toThrow(InvalidProductType::class)
        ->and(fn () => $product->defaultVariant()->update(['type' => 'download']))->toThrow(InvalidProductType::class)
        ->and($product->fresh()->type)->toBe('physical');
});

it('lets a variant override the product type', function () {
    registerDownloadType();
    $product = Product::create(['name' => 'Book', 'price' => Price::of(2000)]);
    $format = ProductAttribute::create(['name' => 'Format', 'slug' => 'format']);
    $ebook = $format->values()->create(['name' => 'Ebook', 'slug' => 'ebook', 'value' => 'ebook']);
    $hardcover = $format->values()->create(['name' => 'Hardcover', 'slug' => 'hardcover', 'value' => 'hardcover']);
    $product->attributeValues()->attach([$ebook->id, $hardcover->id]);
    $product->variantDimensions()->attach($format->id, ['position' => 0]);

    $digital = $product->createVariant([$ebook], ['type' => 'download']);
    $print = $product->createVariant([$hardcover]);

    expect($digital->effectiveType()->key())->toBe('download')
        ->and($digital->has('shipping'))->toBeFalse()
        ->and($print->effectiveType()->key())->toBe('physical')
        ->and($print->has('shipping'))->toBeTrue();
});

it('only adds capabilities when extending a type', function () {
    app(ProductCapabilities::class)->register('gift_wrap');
    $types = app(ProductTypes::class);

    $types->extend('physical', fn (ProductType $type) => $type->withCapability('gift_wrap'));

    expect($types->get('physical')->has('gift_wrap'))->toBeTrue()
        ->and($types->get('physical')->has('shipping'))->toBeTrue()
        ->and(fn () => $types->extend('physical', fn () => ProductType::make('physical')->capabilities(['stock'])))
        ->toThrow(InvalidProductType::class);
});

it('rejects duplicate types and unknown capabilities', function () {
    $types = app(ProductTypes::class);

    expect(fn () => $types->register(ProductType::make('physical')))->toThrow(InvalidProductType::class)
        ->and(fn () => $types->register(ProductType::make('x')->capabilities(['teleport'])))->toThrow(InvalidProductType::class)
        ->and(fn () => $types->get('nope'))->toThrow(InvalidProductType::class);
});

it('snapshots the effective type and capabilities on order items', function () {
    registerDownloadType();
    $product = Product::create(['name' => 'Ebook', 'price' => Price::of(900), 'type' => 'download']);
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
    $cart->add($product->defaultVariant());

    $item = app(Checkout::class)->create($cart, ['customer_email' => 'buyer@example.com', 'customer_name' => 'Buyer'])->order->items->sole();
    app(ProductTypes::class)->extend('download', fn (ProductType $type) => $type->withCapability('stock'));

    expect($item->fresh()->product_type)->toBe('download')
        ->and($item->fresh()->capabilities)->toBe(['download'])
        ->and($item->fresh()->has('download'))->toBeTrue()
        ->and($item->fresh()->has('stock'))->toBeFalse();
});

it('reads legacy order items without a snapshot as physical', function () {
    expect((new OrderItem(['product_type' => null, 'capabilities' => null]))->has('shipping'))->toBeTrue();
});

it('registers and reads types through the facades', function () {
    Larasell\Larasell\Facades\ProductCapabilities::register('download');
    Larasell\Larasell\Facades\ProductTypes::register(ProductType::make('download')->capabilities(['download']));

    expect(Larasell\Larasell\Facades\ProductTypes::has('download'))->toBeTrue()
        ->and(app(ProductTypes::class)->get('download')->has('download'))->toBeTrue()
        ->and(Larasell\Larasell\Facades\ProductTypes::has('nope'))->toBeFalse();
});

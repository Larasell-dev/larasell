<?php

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Facades\Audit;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Catalog\Brands\Actions\CreateBrand;
use Larasell\Larasell\Catalog\Brands\Actions\CreateBrandPayload;
use Larasell\Larasell\Catalog\Brands\Actions\DeleteBrand;
use Larasell\Larasell\Catalog\Brands\Actions\UpdateBrand;
use Larasell\Larasell\Catalog\Brands\Actions\UpdateBrandPayload;
use Larasell\Larasell\Catalog\Brands\BrandId;
use Larasell\Larasell\Catalog\Brands\Exceptions\BrandInUse;
use Larasell\Larasell\Catalog\Brands\Models\Brand;
use Larasell\Larasell\Catalog\Manufacturers\Actions\CreateManufacturer;
use Larasell\Larasell\Catalog\Manufacturers\Actions\CreateManufacturerPayload;
use Larasell\Larasell\Catalog\Manufacturers\Actions\DeleteManufacturer;
use Larasell\Larasell\Catalog\Manufacturers\Actions\UpdateManufacturer;
use Larasell\Larasell\Catalog\Manufacturers\Actions\UpdateManufacturerPayload;
use Larasell\Larasell\Catalog\Manufacturers\Exceptions\ManufacturerInUse;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;
use Larasell\Larasell\Catalog\Manufacturers\Models\Manufacturer;
use Larasell\Larasell\Catalog\Manufacturers\PostalAddress;
use Larasell\Larasell\Catalog\Manufacturers\ResponsiblePerson;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

function gpsrAddress(string $country, string $city): PostalAddress
{
    return new PostalAddress('1 Main Street', '10115', $city, Country::from($country));
}

function acmeBrand(): Brand
{
    return app(CreateBrand::class)->handle(new CreateBrandPayload(new Translatable(['en' => 'Acme', 'de' => 'Akme']), 'acme', new Translatable(['en' => 'Tools'])), origin());
}

/** A manufacturer in the US, so outside the EU, with its responsible person in Germany. */
function usManufacturer(): Manufacturer
{
    return app(CreateManufacturer::class)->handle(new CreateManufacturerPayload(
        'Acme Inc.',
        gpsrAddress('US', 'Springfield'),
        'legal@acme.test',
        responsiblePerson: new ResponsiblePerson('Acme GmbH', gpsrAddress('DE', 'Berlin'), 'eu@acme.test'),
    ), origin());
}

it('updates only the brand fields given, keeping the description unless it is cleared with null', function () {
    // Arrange
    $brand = acmeBrand();

    // Act
    app(UpdateBrand::class)->handle(BrandId::of($brand), new UpdateBrandPayload(slug: 'acme-tools'), origin());
    $kept = $brand->refresh()->description?->all();
    app(UpdateBrand::class)->handle(BrandId::of($brand), new UpdateBrandPayload(description: null), origin());

    // Assert
    expect($kept)->toBe(['en' => 'Tools'])
        ->and($brand->refresh()->only('slug', 'description'))->toBe(['slug' => 'acme-tools', 'description' => null])
        ->and($brand->name->all())->toEqual(['en' => 'Acme', 'de' => 'Akme']);
});

it('names the responsible person as EU contact of a manufacturer outside the EU, and the manufacturer itself inside', function () {
    // Arrange
    $manufacturer = usManufacturer();

    // Act
    $outside = $manufacturer->refresh()->euContact();
    app(UpdateManufacturer::class)->handle(ManufacturerId::of($manufacturer), new UpdateManufacturerPayload(address: gpsrAddress('AT', 'Wien')), origin());
    $inside = $manufacturer->refresh()->euContact();

    // Assert
    expect($outside)->toBeInstanceOf(ResponsiblePerson::class)
        ->and($outside->name)->toBe('Acme GmbH')
        ->and($outside->address->country)->toBe(Country::DE)
        ->and($inside)->toBe($manufacturer)
        ->and($manufacturer->responsible_person?->email)->toBe('eu@acme.test');
});

it('sets, keeps and removes a product\'s brand and manufacturer', function () {
    // Arrange
    $brand = acmeBrand();
    $manufacturer = usManufacturer();
    $product = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => 'Hammer', 'price' => Price::of(1000)], brandId: BrandId::of($brand), manufacturerId: ManufacturerId::of($manufacturer)), origin());

    // Act
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['name' => 'Big hammer']), origin());
    $kept = $product->refresh()->only('brand_id', 'manufacturer_id');
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(brandId: null), origin());

    // Assert
    expect($kept)->toBe(['brand_id' => $brand->id, 'manufacturer_id' => $manufacturer->id])
        ->and($product->refresh()->brand)->toBeNull()
        ->and($product->manufacturer?->name)->toBe('Acme Inc.');
});

it('refuses to delete a brand or manufacturer products use, and deletes them once none do', function () {
    // Arrange
    $brand = acmeBrand();
    $manufacturer = usManufacturer();
    $product = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => 'Hammer', 'price' => Price::of(1000)], brandId: BrandId::of($brand), manufacturerId: ManufacturerId::of($manufacturer)), origin());

    // Act
    $brandInUse = rescue(fn () => app(DeleteBrand::class)->handle(BrandId::of($brand), origin()), fn ($e) => $e, false);
    $manufacturerInUse = rescue(fn () => app(DeleteManufacturer::class)->handle(ManufacturerId::of($manufacturer), origin()), fn ($e) => $e, false);
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(brandId: null, manufacturerId: null), origin());
    app(DeleteBrand::class)->handle(BrandId::of($brand), origin());
    app(DeleteManufacturer::class)->handle(ManufacturerId::of($manufacturer), origin());

    // Assert
    expect($brandInUse)->toBeInstanceOf(BrandInUse::class)
        ->and($brandInUse->products)->toBe(1)
        ->and($manufacturerInUse)->toBeInstanceOf(ManufacturerInUse::class)
        ->and(Brand::query()->count() + Manufacturer::query()->count())->toBe(0);
});

it('audits brand and manufacturer changes', function () {
    // Arrange
    Audit::fake();

    // Act
    $brand = acmeBrand();
    app(UpdateBrand::class)->handle(BrandId::of($brand), new UpdateBrandPayload(slug: 'acme-tools'), origin());
    app(DeleteBrand::class)->handle(BrandId::of($brand), origin());
    $manufacturer = usManufacturer();

    // Assert
    $records = [];
    Audit::assertRecorded(AuditRecord::class, function ($record) use (&$records) {
        $records[] = [$record->event(), $record->properties()];

        return true;
    });
    expect($records)->toEqual([
        ['brand.created', ['slug' => 'acme', 'name' => ['en' => 'Acme', 'de' => 'Akme']]],
        ['brand.updated', ['changed' => ['slug']]],
        ['brand.deleted', []],
        ['manufacturer.created', ['name' => 'Acme Inc.', 'country' => 'US']],
    ]);
});

it('snapshots the brand and manufacturer on order items, unchanged when they change later', function () {
    // Arrange
    $manufacturer = usManufacturer();
    $product = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => 'Hammer', 'price' => Price::of(1000)], brandId: BrandId::of(acmeBrand()), manufacturerId: ManufacturerId::of($manufacturer)), origin());
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
    $cart->add($product);

    // Act
    $item = app(Checkout::class)->create($cart, ['customer_email' => 'gpsr@example.com', 'customer_name' => 'Customer'])->order->items->sole();
    app(UpdateManufacturer::class)->handle(ManufacturerId::of($manufacturer), new UpdateManufacturerPayload(name: 'Renamed', responsiblePerson: null), origin());

    // Assert
    $item->refresh();
    expect($item->brand_name?->all())->toEqual(['en' => 'Acme', 'de' => 'Akme'])
        ->and($item->manufacturer['name'])->toBe('Acme Inc.')
        ->and($item->manufacturer['address']['country'])->toBe('US')
        ->and($item->manufacturer['responsible_person']['name'])->toBe('Acme GmbH');
});

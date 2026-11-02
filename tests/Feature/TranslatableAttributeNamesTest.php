<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

it('casts attribute and value names from translations', function () {
    $color = ProductAttribute::create(['slug' => 'color', 'name' => ['en' => 'Color', 'de' => 'Farbe']]);
    $red = $color->values()->create(['slug' => 'red', 'name' => ['en' => 'Red', 'de' => 'Rot'], 'value' => 'red']);

    App::setLocale('de');

    expect($color->fresh()->name)->toBeInstanceOf(Translatable::class)
        ->and($color->fresh()->name->get())->toBe('Farbe')
        ->and($red->fresh()->name->get())->toBe('Rot')
        ->and(json_decode(DB::table('larasell_product_attributes')->where('id', $color->id)->value('name'), true))
        ->toEqualCanonicalizing(['en' => 'Color', 'de' => 'Farbe'])
        ->and(json_decode(DB::table('larasell_product_attribute_values')->where('id', $red->id)->value('name'), true))
        ->toEqualCanonicalizing(['en' => 'Red', 'de' => 'Rot']);
});

it('accepts string attribute and value names for the current locale', function () {
    App::setLocale('de');

    $color = ProductAttribute::create(['slug' => 'color', 'name' => 'Farbe']);
    $red = $color->values()->create(['slug' => 'red', 'name' => 'Rot', 'value' => 'red']);

    expect($color->fresh()->name->all())->toBe(['de' => 'Farbe'])
        ->and($red->fresh()->name->all())->toBe(['de' => 'Rot']);
});

it('names variant options in the current locale', function () {
    $product = Product::create([
        'slug' => 'shirt',
        'name' => ['en' => 'Shirt', 'de' => 'Hemd'],
        'price' => Price::of(1000),
        'status' => Visibility::Visible,
    ]);
    $size = ProductAttribute::create(['slug' => 'size', 'name' => ['en' => 'Size', 'de' => 'Größe']]);
    $small = $size->values()->create(['slug' => 'small', 'name' => ['en' => 'Small', 'de' => 'Klein'], 'value' => 'small']);
    $medium = $size->values()->create(['slug' => 'medium', 'name' => ['en' => 'Medium', 'de' => 'Mittel'], 'value' => 'medium']);
    $color = ProductAttribute::create(['slug' => 'color', 'name' => ['en' => 'Color']]);
    $black = $color->values()->create(['slug' => 'black', 'name' => ['en' => 'Black', 'de' => 'Schwarz'], 'value' => 'black']);
    $product->attributeValues()->attach([$small->id, $medium->id, $black->id]);
    $product->generateVariants([$size, $color])->each->update(['status' => Visibility::Visible]);

    App::setLocale('de');
    $variant = $product->variantFor(['size' => 'small', 'color' => 'black']);

    expect($variant->name())->toBe('Klein / Schwarz')
        ->and($variant->options()['size']['attribute_name'])->toBe('Größe')
        ->and($variant->options()['size']['value_name'])->toBe('Klein')
        ->and($variant->options()['color']['attribute_name'])->toBe('Color');
});

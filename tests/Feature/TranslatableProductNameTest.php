<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

it('casts a product name from translations', function () {
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => ['en' => 'Desk lamp', 'de' => 'Schreibtischlampe'],
        'price' => Price::of(4999),
    ]);

    App::setLocale('de');
    $name = $product->fresh()->name;

    $storedName = DB::table('larasell_products')->where('id', $product->id)->value('name');

    expect($name)->toBeInstanceOf(Translatable::class)
        ->and($name->get())->toBe('Schreibtischlampe')
        ->and(json_decode($storedName, true))->toBe(['en' => 'Desk lamp', 'de' => 'Schreibtischlampe']);
});

it('accepts a string product name for the current locale', function () {
    App::setLocale('de');

    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Schreibtischlampe',
        'price' => Price::of(4999),
    ]);

    expect($product->fresh()->name->all())->toBe(['de' => 'Schreibtischlampe']);
});

it('casts a nullable product description from translations', function () {
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'description' => ['en' => 'A focused task light.', 'de' => 'Eine fokussierte Arbeitsleuchte.'],
        'price' => Price::of(4999),
    ]);

    App::setLocale('de');
    $description = $product->fresh()->description;
    $storedDescription = DB::table('larasell_products')->where('id', $product->id)->value('description');

    expect($description)->toBeInstanceOf(Translatable::class)
        ->and($description->get())->toBe('Eine fokussierte Arbeitsleuchte.')
        ->and(json_decode($storedDescription, true))->toBe([
            'en' => 'A focused task light.',
            'de' => 'Eine fokussierte Arbeitsleuchte.',
        ]);
});

it('allows a product description to be null', function () {
    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'description' => null,
        'price' => Price::of(4999),
    ]);

    expect($product->fresh()->description)->toBeNull();
});

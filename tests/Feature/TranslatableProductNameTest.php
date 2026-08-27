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

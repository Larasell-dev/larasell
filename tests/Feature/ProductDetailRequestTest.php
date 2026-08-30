<?php

use Larasell\Larasell\Http\Requests\ProductDetailRequest;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

beforeEach(function () {
    Route::get('/p/{product}', function (ProductDetailRequest $request): array {
        return ['id' => $request->product()->getKey()];
    });
});

it('resolves a product by its slug in the current locale', function () {
    App::setLocale('de');
    $product = Product::create([
        'slug' => ['en' => 'desk-lamp', 'de' => 'schreibtischlampe'],
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);

    $this->getJson('/p/schreibtischlampe')
        ->assertOk()
        ->assertExactJson(['id' => $product->getKey()]);
});

<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('stores nullable product identifiers', function () {
    $product = identifiedProduct([
        'sku' => 'MUG-CLASSIC',
        'barcode' => '04012345678901',
    ])->fresh();

    expect($product->sku)->toBe('MUG-CLASSIC')
        ->and($product->barcode)->toBe('04012345678901');

    $unidentified = identifiedProduct()->fresh();

    expect($unidentified->sku)->toBeNull()
        ->and($unidentified->barcode)->toBeNull();
});

it('requires non-null product identifiers to be unique', function (string $column) {
    identifiedProduct([$column => 'DUPLICATE']);

    expect(fn () => DB::transaction(
        fn () => identifiedProduct([$column => 'DUPLICATE'])
    ))
        ->toThrow(QueryException::class);
})->with(['sku', 'barcode']);

function identifiedProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ], $attributes));
}

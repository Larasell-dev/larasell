<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Price;

it('exposes the first ordered product image as the thumbnail', function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');

    $product = Product::query()->create([
        'slug' => 'desk-lamp',
        'name' => 'Desk lamp',
        'price' => Price::of(4999),
    ]);
    $second = ProductImage::query()->create([
        'file' => UploadedFile::fake()->image('side.jpg'),
        'alt' => 'Side',
    ]);
    $first = ProductImage::query()->create([
        'file' => UploadedFile::fake()->image('front.jpg'),
        'alt' => 'Front',
    ]);

    $product->images()->attach($second, ['position' => 1]);
    $product->images()->attach($first, ['position' => 0]);

    $product->load('images');

    expect($product->thumbnail)->not->toBeNull()
        ->and($product->thumbnail->is($first))->toBeTrue()
        ->and($product->defaultVariant()->thumbnail?->is($first))->toBeTrue();
});

it('returns a null thumbnail when a product has no images', function () {
    $product = Product::query()->create([
        'slug' => 'plain-tee',
        'name' => 'Plain tee',
        'price' => Price::of(1000),
    ]);

    expect($product->thumbnail)->toBeNull()
        ->and($product->defaultVariant()->thumbnail)->toBeNull();
});

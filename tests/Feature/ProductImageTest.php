<?php

use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Images\ColorPlaceholderGenerator;
use Larasell\Larasell\Images\LqipPlaceholderGenerator;
use Larasell\Larasell\Images\Placeholder;
use Larasell\Larasell\Models\ProductImage;

function storeSolidPng(string $path, int $red, int $green, int $blue): void
{
    $canvas = imagecreatetruecolor(8, 8);
    $color = imagecolorallocate($canvas, $red, $green, $blue);
    imagefill($canvas, 0, 0, $color);
    ob_start();
    imagepng($canvas);
    $png = ob_get_clean();
    imagedestroy($canvas);

    Storage::disk((string) config('larasell.images.disk'))->put($path, $png);
}

beforeEach(function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');
});

it('creates a product image from a path', function () {
    $image = ProductImage::query()->create([
        'path' => 'products/front.jpg',
        'alt' => 'Front',
    ]);

    expect($image->path)->toBe('products/front.jpg')
        ->and($image->isSvg())->toBeFalse()
        ->and($image->placeholder)->toBeNull();
});

it('detects svg product images by path', function () {
    $image = ProductImage::query()->create(['path' => 'products/icon.svg']);

    expect($image->isSvg())->toBeTrue()
        ->and($image->placeholder)->toBeNull();
});

it('detects svg product images by original file name', function () {
    $image = ProductImage::query()->create([
        'path' => 'products/hashed',
        'meta' => ['original_name' => 'icon.SVG'],
    ]);

    expect($image->isSvg())->toBeTrue()
        ->and($image->placeholder)->toBeNull();
});

it('detects svg product images by mime type', function () {
    $image = ProductImage::query()->create([
        'path' => 'products/hashed',
        'meta' => ['mime_type' => 'image/svg+xml'],
    ]);

    expect($image->isSvg())->toBeTrue()
        ->and($image->placeholder)->toBeNull();
});

it('never stores a placeholder for svg originals even when a raster file exists', function () {
    storeSolidPng('products/icon.svg', 255, 0, 0);

    $image = ProductImage::query()->create(['path' => 'products/icon.svg']);

    expect($image->placeholder)->toBeNull();
});

it('does not generate a placeholder by default', function () {
    storeSolidPng('products/front.png', 255, 0, 0);

    $image = ProductImage::query()->create(['path' => 'products/front.png']);

    expect($image->placeholder)->toBeNull();
});

it('generates an lqip placeholder when that generator is configured', function () {
    config()->set('larasell.images.placeholder', LqipPlaceholderGenerator::class);
    storeSolidPng('products/front.png', 255, 0, 0);

    $image = ProductImage::query()->create(['path' => 'products/front.png']);

    expect($image->placeholder)->not->toBeNull()
        ->and($image->placeholder->type)->toBe('lqip')
        ->and($image->placeholder->value)->toStartWith('data:image/jpeg;base64,')
        ->and($image->placeholder->color)->toBe('#ff0000');
});

it('uses the configured placeholder generator', function () {
    config()->set('larasell.images.placeholder', ColorPlaceholderGenerator::class);
    storeSolidPng('products/front.png', 0, 128, 0);

    $image = ProductImage::query()->create(['path' => 'products/front.png']);

    expect($image->placeholder?->toArray())->toBe([
        'type' => 'color',
        'value' => '#008000',
        'color' => '#008000',
    ]);
});

it('keeps an explicitly provided placeholder', function () {
    $image = ProductImage::query()->create([
        'path' => 'products/front.jpg',
        'placeholder' => new Placeholder('color', '#abcdef', '#abcdef'),
    ]);

    expect($image->placeholder?->toArray())->toBe([
        'type' => 'color',
        'value' => '#abcdef',
        'color' => '#abcdef',
    ]);
});

it('does not regenerate the placeholder when the path is unchanged', function () {
    $image = ProductImage::query()->create([
        'path' => 'products/front.jpg',
        'placeholder' => new Placeholder('color', '#abcdef', '#abcdef'),
    ]);

    $image->update(['alt' => 'Front']);

    expect($image->placeholder?->value)->toBe('#abcdef');
});

it('regenerates the placeholder when the path changes', function () {
    storeSolidPng('products/red.png', 255, 0, 0);
    storeSolidPng('products/blue.png', 0, 0, 255);
    config()->set('larasell.images.placeholder', ColorPlaceholderGenerator::class);

    $image = ProductImage::query()->create(['path' => 'products/red.png']);

    expect($image->placeholder?->color)->toBe('#ff0000');

    $image->update(['path' => 'products/blue.png']);

    expect($image->fresh()->placeholder?->color)->toBe('#0000ff');
});

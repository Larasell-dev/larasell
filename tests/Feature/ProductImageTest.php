<?php

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Images\ColorPlaceholderGenerator;
use Larasell\Larasell\Images\LqipPlaceholderGenerator;
use Larasell\Larasell\Images\Placeholder;
use Larasell\Larasell\Models\ProductImage;

function uploadedSolidPng(string $name, int $red, int $green, int $blue): UploadedFile
{
    $canvas = imagecreatetruecolor(8, 8);
    $color = imagecolorallocate($canvas, $red, $green, $blue);
    imagefill($canvas, 0, 0, $color);
    ob_start();
    imagepng($canvas);
    $png = ob_get_clean();
    imagedestroy($canvas);

    return UploadedFile::fake()->createWithContent($name, $png);
}

beforeEach(function () {
    Storage::fake('product-images');
    config()->set('larasell.images.disk', 'product-images');
});

it('stores an uploaded file on the configured disk', function () {
    $upload = UploadedFile::fake()->image('side.jpg');

    $image = ProductImage::query()->create([
        'file' => $upload,
        'alt' => 'Bicycle from the side',
    ]);

    Storage::disk('product-images')->assertExists($image->file);

    expect($image->file)->toStartWith('larasell/products/')
        ->and($image->alt)->toBe('Bicycle from the side');
});

it('stores a local file on the configured disk', function () {
    $upload = UploadedFile::fake()->image('side.jpg');

    $image = ProductImage::query()->create([
        'file' => new File($upload->getRealPath()),
        'alt' => 'Bicycle from the side',
    ]);

    Storage::disk('product-images')->assertExists($image->file);

    expect($image->file)->toStartWith('larasell/products/');
});

it('rejects a non-file value', function () {
    ProductImage::query()->create([
        'file' => 'products/front.jpg',
        'alt' => 'Front',
    ]);
})->throws(InvalidArgumentException::class);

it('detects svg product images by file name', function () {
    $image = ProductImage::query()->create([
        'file' => UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml'),
    ]);

    expect($image->isSvg())->toBeTrue()
        ->and($image->placeholder)->toBeNull();
});

it('detects svg product images by original file name', function () {
    $image = ProductImage::query()->create([
        'file' => UploadedFile::fake()->create('hashed', 10, 'image/png'),
        'meta' => ['original_name' => 'icon.SVG'],
    ]);

    expect($image->isSvg())->toBeTrue()
        ->and($image->placeholder)->toBeNull();
});

it('detects svg product images by mime type', function () {
    $image = ProductImage::query()->create([
        'file' => UploadedFile::fake()->create('hashed', 10, 'image/png'),
        'meta' => ['mime_type' => 'image/svg+xml'],
    ]);

    expect($image->isSvg())->toBeTrue()
        ->and($image->placeholder)->toBeNull();
});

it('never stores a placeholder for svg originals even when a raster file exists', function () {
    $image = ProductImage::query()->create([
        'file' => uploadedSolidPng('icon.svg', 255, 0, 0),
    ]);

    expect($image->placeholder)->toBeNull();
});

it('does not generate a placeholder by default', function () {
    $image = ProductImage::query()->create([
        'file' => uploadedSolidPng('front.png', 255, 0, 0),
    ]);

    expect($image->placeholder)->toBeNull();
});

it('generates an lqip placeholder when that generator is configured', function () {
    config()->set('larasell.images.placeholder', LqipPlaceholderGenerator::class);

    $image = ProductImage::query()->create([
        'file' => uploadedSolidPng('front.png', 255, 0, 0),
    ]);

    expect($image->placeholder)->not->toBeNull()
        ->and($image->placeholder->type)->toBe('lqip')
        ->and($image->placeholder->value)->toStartWith('data:image/jpeg;base64,')
        ->and($image->placeholder->color)->toBe('#ff0000');
});

it('uses the configured placeholder generator', function () {
    config()->set('larasell.images.placeholder', ColorPlaceholderGenerator::class);

    $image = ProductImage::query()->create([
        'file' => uploadedSolidPng('front.png', 0, 128, 0),
    ]);

    expect($image->placeholder?->toArray())->toBe([
        'type' => 'color',
        'value' => '#008000',
        'color' => '#008000',
    ]);
});

it('keeps an explicitly provided placeholder', function () {
    $image = ProductImage::query()->create([
        'file' => UploadedFile::fake()->image('front.jpg'),
        'placeholder' => new Placeholder('color', '#abcdef', '#abcdef'),
    ]);

    expect($image->placeholder?->toArray())->toBe([
        'type' => 'color',
        'value' => '#abcdef',
        'color' => '#abcdef',
    ]);
});

it('does not regenerate the placeholder when the file is unchanged', function () {
    $image = ProductImage::query()->create([
        'file' => UploadedFile::fake()->image('front.jpg'),
        'placeholder' => new Placeholder('color', '#abcdef', '#abcdef'),
    ]);

    $image->update(['alt' => 'Front']);

    expect($image->placeholder?->value)->toBe('#abcdef');
});

it('regenerates the placeholder when the file changes', function () {
    config()->set('larasell.images.placeholder', ColorPlaceholderGenerator::class);

    $image = ProductImage::query()->create([
        'file' => uploadedSolidPng('red.png', 255, 0, 0),
    ]);

    expect($image->placeholder?->color)->toBe('#ff0000');

    $image->update([
        'file' => uploadedSolidPng('blue.png', 0, 0, 255),
    ]);

    expect($image->fresh()->placeholder?->color)->toBe('#0000ff');
});

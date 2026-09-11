<?php

use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Images\ColorPlaceholderGenerator;
use Larasell\Larasell\Images\Placeholder;
use Larasell\Larasell\Models\ProductImage;

function refreshPlaceholderCommandPng(string $path, int $red, int $green, int $blue): void
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

it('rebuilds placeholders with the configured generator', function () {
    refreshPlaceholderCommandPng('products/front.png', 255, 0, 0);
    $image = ProductImage::query()->create([
        'path' => 'products/front.png',
        'placeholder' => new Placeholder('lqip', 'data:image/jpeg;base64,old', '#ffffff'),
    ]);
    $svg = ProductImage::query()->create(['path' => 'products/icon.svg']);

    config()->set('larasell.images.placeholder', ColorPlaceholderGenerator::class);

    $this->artisan('larasell:refresh-placeholders', ['--batch-size' => 1])
        ->expectsOutput('Refreshed placeholders for 2 images.')
        ->assertSuccessful();

    expect($image->fresh()->placeholder?->toArray())->toBe([
        'type' => 'color',
        'value' => '#ff0000',
        'color' => '#ff0000',
    ])
        ->and($svg->fresh()->placeholder)->toBeNull();
});

it('rejects invalid placeholder refresh batch sizes', function () {
    $this->artisan('larasell:refresh-placeholders', ['--batch-size' => 0])
        ->expectsOutput('The batch size must be a positive integer.')
        ->assertFailed();
});

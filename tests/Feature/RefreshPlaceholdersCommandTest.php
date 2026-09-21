<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Images\ColorPlaceholderGenerator;
use Larasell\Larasell\Images\Placeholder;
use Larasell\Larasell\Models\ProductImage;

function refreshPlaceholderCommandPng(string $name, int $red, int $green, int $blue): UploadedFile
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

it('rebuilds placeholders with the configured generator', function () {
    $image = ProductImage::query()->create([
        'file' => refreshPlaceholderCommandPng('front.png', 255, 0, 0),
        'placeholder' => new Placeholder('lqip', 'data:image/jpeg;base64,old', '#ffffff'),
    ]);
    $svg = ProductImage::query()->create([
        'file' => UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml'),
    ]);

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

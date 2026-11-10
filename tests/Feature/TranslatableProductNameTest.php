<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Exceptions\InvalidRichText;
use Larasell\Larasell\Price;
use Larasell\Larasell\RichText\RichText;
use Larasell\Larasell\RichText\TranslatableRichText;
use Larasell\Larasell\Translatable;

it('casts a product name from translations', function () {
    $product = Product::query()->create([
        'name' => ['en' => 'Desk lamp', 'de' => 'Schreibtischlampe'],
        'price' => Price::of(4999),
    ]);

    App::setLocale('de');
    $name = $product->fresh()->name;

    $storedName = DB::table('larasell_products')->where('id', $product->id)->value('name');

    expect($name)->toBeInstanceOf(Translatable::class)
        ->and($name->get())->toBe('Schreibtischlampe')
        ->and(json_decode($storedName, true))->toEqualCanonicalizing(['en' => 'Desk lamp', 'de' => 'Schreibtischlampe']);
});

it('accepts a string product name for the current locale', function () {
    App::setLocale('de');

    $product = Product::query()->create([
        'name' => 'Schreibtischlampe',
        'price' => Price::of(4999),
    ]);

    expect($product->fresh()->name->all())->toBe(['de' => 'Schreibtischlampe']);
});

it('reads a product description as rich text per locale, with plain text as paragraphs', function () {
    $german = ['version' => 1, 'blocks' => [['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Leuchte']]]]];
    $product = Product::query()->create([
        'name' => 'Desk lamp',
        'description' => ['en' => 'A focused task light.', 'de' => json_encode($german)],
        'price' => Price::of(4999),
    ]);

    App::setLocale('de');
    $description = $product->fresh()->description;
    $stored = json_decode(DB::table('larasell_products')->where('id', $product->id)->value('description'), true);

    expect($description)->toBeInstanceOf(TranslatableRichText::class)
        ->and($description->get()->toArray())->toBe($german)
        ->and($description->get('en')->toHtml())->toBe('<p>A focused task light.</p>')
        ->and(json_decode($stored['de'], true))->toBe($german)
        ->and(json_decode($stored['en'], true)['blocks'][0]['children'][0]['text'])->toBe('A focused task light.');
});

it('changes one locale of a product description, and drops it when emptied', function () {
    $product = Product::query()->create([
        'name' => 'Desk lamp',
        'description' => ['en' => 'Light.', 'de' => 'Licht.'],
        'price' => Price::of(4999),
    ]);

    $product->update(['description' => $product->description->with('de', RichText::fromArray(['version' => 1, 'blocks' => []]))]);

    expect(array_keys($product->fresh()->description->all()))->toBe(['en']);
});

it('rejects a product description that is not valid rich text', function () {
    Product::query()->create([
        'name' => 'Desk lamp',
        'description' => json_encode(['version' => 1, 'blocks' => [['type' => 'table']]]),
        'price' => Price::of(4999),
    ]);
})->throws(InvalidRichText::class);

it('allows a product description to be null', function () {
    $product = Product::query()->create([
        'name' => 'Desk lamp',
        'description' => null,
        'price' => Price::of(4999),
    ]);

    expect($product->fresh()->description)->toBeNull();
});

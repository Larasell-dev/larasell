<?php

use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Catalog\Actions\UpdateProductVariant;
use Larasell\Larasell\Catalog\Exceptions\InvalidInheritance;
use Larasell\Larasell\Catalog\Exceptions\InvalidProductType;
use Larasell\Larasell\Catalog\InheritanceState;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductCapabilities;
use Larasell\Larasell\Catalog\ProductType;
use Larasell\Larasell\Catalog\ProductTypes;
use Larasell\Larasell\Catalog\VariantId;
use Larasell\Larasell\Dimensions;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Price;
use Larasell\Larasell\RichText\RichText;
use Larasell\Larasell\RichText\TranslatableRichText;
use Larasell\Larasell\Weight;

function variantText(string $text): RichText
{
    return RichText::fromArray([[
        '_type' => 'block', '_key' => 'a', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'span', '_key' => 'b', 'text' => $text, 'marks' => []]],
    ]]);
}

function inheritingProduct(array $attributes = []): Product
{
    return Product::create([
        'name' => ['en' => 'Basic T-Shirt', 'de' => 'Basic-T-Shirt'],
        'price' => Price::of(1000),
        'weight' => Weight::of(180, 'g'),
        'dimensions' => Dimensions::of(30, 20, 2, 'cm'),
        'country_of_origin' => Country::PT,
        'hs_code' => '610910',
        ...$attributes,
    ]);
}

it('keeps identifiers on the variant only', function () {
    $product = inheritingProduct();
    $variant = $product->defaultVariant();

    $variant->update(['sku' => 'TSH-1', 'barcode' => '04012345678901', 'mpn' => 'MFR-77']);

    expect(Schema::hasColumns('larasell_products', ['sku']))->toBeFalse()
        ->and(Schema::hasColumns('larasell_products', ['barcode']))->toBeFalse()
        ->and($variant->fresh()->sku)->toBe('TSH-1')
        ->and($variant->fresh()->barcode)->toBe('04012345678901')
        ->and($variant->fresh()->mpn)->toBe('MFR-77');
});

it('allows two variants with the same manufacturer part number', function () {
    $first = inheritingProduct()->defaultVariant();
    $second = inheritingProduct()->defaultVariant();

    $first->update(['mpn' => 'MFR-77']);
    $second->update(['mpn' => 'MFR-77']);

    expect($second->fresh()->mpn)->toBe('MFR-77');
});

it('inherits every inheritable field by default, following later product changes', function () {
    $product = inheritingProduct();
    $variant = $product->defaultVariant();

    $product->update(['weight' => Weight::of(200, 'g'), 'country_of_origin' => Country::BD]);
    $variant = $variant->fresh();

    expect($variant->inheritance('weight'))->toBe(InheritanceState::Inherited)
        ->and($variant->inheritance('dimensions'))->toBe(InheritanceState::Inherited)
        ->and($variant->inheritance('type'))->toBe(InheritanceState::Inherited)
        ->and($variant->effectiveWeight()?->grams())->toBe('200')
        ->and($variant->effectiveDimensions()?->equals(Dimensions::of(30, 20, 2, 'cm')))->toBeTrue()
        ->and($variant->effectiveCountryOfOrigin())->toBe(Country::BD)
        ->and($variant->effectiveHsCode())->toBe('610910');
});

it('keeps an overridden value when the product changes', function () {
    $product = inheritingProduct();
    $variant = $product->defaultVariant();

    $variant->override('weight', Weight::of(210, 'g'))->override('hs_code', '610990')->save();
    $product->update(['weight' => Weight::of(150, 'g'), 'hs_code' => '610000']);
    $variant = $variant->fresh();

    expect($variant->inheritance('weight'))->toBe(InheritanceState::Overridden)
        ->and($variant->effectiveWeight()?->grams())->toBe('210')
        ->and($variant->effectiveHsCode())->toBe('610990');
});

it('has no value for a cleared field even though the product has one', function () {
    $variant = inheritingProduct()->defaultVariant();

    $variant->clear('country_of_origin')->clear('dimensions')->save();
    $variant = $variant->fresh();

    expect($variant->inheritance('country_of_origin'))->toBe(InheritanceState::Cleared)
        ->and($variant->effectiveCountryOfOrigin())->toBeNull()
        ->and($variant->effectiveDimensions())->toBeNull()
        ->and($variant->effectiveWeight())->not->toBeNull();
});

it('inherits again after an override, dropping the variant value', function () {
    $variant = inheritingProduct()->defaultVariant();
    $variant->override('weight', Weight::of(210, 'g'))->save();

    $variant->inherit('weight')->save();
    $variant = $variant->fresh();

    expect($variant->inheritance('weight'))->toBe(InheritanceState::Inherited)
        ->and($variant->weight)->toBeNull()
        ->and($variant->effectiveWeight()?->grams())->toBe('180');
});

it('rejects an override without a value', function () {
    $variant = inheritingProduct()->defaultVariant();

    expect(fn () => $variant->update(['weight_inheritance' => InheritanceState::Overridden]))
        ->toThrow(InvalidInheritance::class);
});

it('rejects a value on a field that is not overridden', function () {
    $variant = inheritingProduct()->defaultVariant();

    expect(fn () => $variant->update(['hs_code' => '610990']))
        ->toThrow(InvalidInheritance::class);
});

it('rejects inheritance states for fields that are not inheritable', function () {
    $variant = inheritingProduct()->defaultVariant();

    expect(fn () => $variant->override('sku', 'X'))->toThrow(InvalidInheritance::class)
        ->and(fn () => $variant->inheritance('price'))->toThrow(InvalidInheritance::class);
});

it('lets a variant have its own type but never clear it', function () {
    app(ProductCapabilities::class)->register('download');
    app(ProductTypes::class)->register(ProductType::make('download')->label('Download')->capabilities(['download']));
    $product = inheritingProduct();

    $digital = $product->variants()->create([
        'combination_key' => 'digital',
        'type_inheritance' => InheritanceState::Overridden,
        'type' => 'download',
    ]);

    expect($digital->fresh()->effectiveType()->key())->toBe('download')
        ->and($product->defaultVariant()->effectiveType()->key())->toBe('physical')
        ->and(fn () => $product->defaultVariant()->clear('type'))->toThrow(InvalidInheritance::class)
        ->and(fn () => $digital->fresh()->inherit('type')->save())->toThrow(InvalidProductType::class);
});

it('resolves the name per locale, an own name for one locale and the inherited one for others', function () {
    $variant = inheritingProduct()->defaultVariant();

    $variant->overrideTranslation('name', 'de', 'T-Shirt, extra lang')->save();
    $variant = $variant->fresh();

    expect($variant->translationInheritance('name', 'de'))->toBe(InheritanceState::Overridden)
        ->and($variant->translationInheritance('name', 'en'))->toBe(InheritanceState::Inherited)
        ->and($variant->name('de'))->toBe('T-Shirt, extra lang')
        ->and($variant->name('en'))->toBe('Basic T-Shirt')
        ->and(fn () => $variant->clearTranslation('name', 'en'))->toThrow(InvalidInheritance::class);
});

it('resolves the description per locale with all three states', function () {
    $product = inheritingProduct(['description' => new TranslatableRichText([
        'en' => variantText('Soft cotton.'),
        'de' => variantText('Weiche Baumwolle.'),
        'fr' => variantText('Coton doux.'),
    ])]);
    $variant = $product->defaultVariant();

    $variant->overrideTranslation('description', 'de', variantText('Extra lang.'))
        ->clearTranslation('description', 'fr')
        ->save();
    $variant = $variant->fresh();

    expect($variant->description('de')?->toPlainText())->toBe('Extra lang.')
        ->and($variant->description('en')?->toPlainText())->toBe('Soft cotton.')
        ->and($variant->description('fr'))->toBeNull()
        ->and($variant->translationInheritance('description', 'fr'))->toBe(InheritanceState::Cleared);
});

it('prefers the product in a more specific locale over the variant in a broader one', function () {
    $product = inheritingProduct(['name' => ['en' => 'Shirt', 'de' => 'Hemd', 'de-AT' => 'Leiberl']]);
    $variant = $product->defaultVariant();

    $variant->overrideTranslation('name', 'de', 'Langes Hemd')->save();
    $variant = $variant->fresh();

    expect($variant->name('de-AT'))->toBe('Leiberl')
        ->and($variant->name('de-DE'))->toBe('Langes Hemd')
        ->and($variant->name('de'))->toBe('Langes Hemd');
});

it('updates inheritance through the variant action', function () {
    $variant = inheritingProduct()->defaultVariant();

    app(UpdateProductVariant::class)->handle(VariantId::of($variant), [
        'weight_inheritance' => InheritanceState::Overridden,
        'weight' => Weight::of(250, 'g'),
        'country_of_origin_inheritance' => InheritanceState::Cleared,
        'name' => ['de' => 'Extra lang'],
    ]);
    $variant = $variant->fresh();

    expect($variant->effectiveWeight()?->grams())->toBe('250')
        ->and($variant->effectiveCountryOfOrigin())->toBeNull()
        ->and($variant->name('de'))->toBe('Extra lang');
});

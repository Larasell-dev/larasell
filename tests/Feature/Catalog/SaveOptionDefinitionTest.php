<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Options\Actions\DeleteOptionDefinition;
use Larasell\Larasell\Catalog\Options\Actions\OptionValueDraft;
use Larasell\Larasell\Catalog\Options\Actions\SaveOptionDefinition;
use Larasell\Larasell\Catalog\Options\Actions\SaveOptionDefinitionPayload;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionCreated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionDeleted;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueCreated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueDeleted;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueUpdated;
use Larasell\Larasell\Catalog\Options\Exceptions\InvalidOptionValues;
use Larasell\Larasell\Catalog\Options\Exceptions\OptionInUse;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;
use Larasell\Larasell\Catalog\Options\OptionDisplay;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

function optionDraft(?OptionDefinitionValue $value, string $name, string $slug, ?string $swatch = null): OptionValueDraft
{
    return new OptionValueDraft($value ? OptionDefinitionValueId::of($value) : null, new Translatable(['en' => $name]), $slug, $swatch);
}

function optionPayload(array $values, string $name = 'Colour', OptionDisplay $display = OptionDisplay::Swatch): SaveOptionDefinitionPayload
{
    return new SaveOptionDefinitionPayload(new Translatable(['en' => $name]), 'colour', $display, $values);
}

/** Colour with black, white and red; a shirt uses black and white, with one variant each, and a mug offers red without variants. */
function colours(): array
{
    $colour = OptionDefinition::create(['slug' => 'colour', 'name' => ['en' => 'Colour'], 'display' => 'swatch']);
    $black = $colour->values()->create(['slug' => 'black', 'name' => ['en' => 'Black'], 'swatch' => '#000000', 'position' => 1]);
    $white = $colour->values()->create(['slug' => 'white', 'name' => ['en' => 'White'], 'swatch' => '#ffffff', 'position' => 2]);
    $red = $colour->values()->create(['slug' => 'red', 'name' => ['en' => 'Red'], 'swatch' => '#ff0000', 'position' => 3]);
    $shirt = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);
    $shirt->addOption($colour, [$black, $white]);
    $shirt->createCombinations([[$black], [$white]]);
    $mug = Product::create(['name' => 'Mug', 'price' => Price::of(500)]);
    $mug->addOption($colour, [$red]);

    return [$colour, $black, $white, $red, $shirt, $mug];
}

it('creates an option with its display and its values with swatches, in the given order', function () {
    // Arrange
    Event::fake([OptionDefinitionCreated::class, OptionDefinitionValueCreated::class]);

    // Act
    $colour = app(SaveOptionDefinition::class)->handle(null, optionPayload([
        optionDraft(null, 'White', 'white', '#ffffff'),
        optionDraft(null, 'Black', 'black', '#000000'),
    ]));

    // Assert
    expect($colour->display)->toBe('swatch')
        ->and($colour->values->map(fn ($value) => [$value->slug, $value->swatch, $value->position])->all())
        ->toBe([['white', '#ffffff', 1], ['black', '#000000', 2]]);
    Event::assertDispatchedTimes(OptionDefinitionCreated::class, 1);
    Event::assertDispatchedTimes(OptionDefinitionValueCreated::class, 2);
});

it('updates, creates, reorders and deletes values to match the drafts, swapping slugs', function () {
    // Arrange
    [$colour, $black, $white, $red, $shirt, $mug] = colours();
    $green = $colour->values()->create(['slug' => 'green', 'name' => ['en' => 'Green'], 'position' => 4]);
    Event::fake([OptionDefinitionValueCreated::class, OptionDefinitionValueUpdated::class, OptionDefinitionValueDeleted::class]);

    // Act
    app(SaveOptionDefinition::class)->handle(OptionDefinitionId::of($colour), optionPayload([
        optionDraft($white, 'White', 'black', '#fafafa'),
        optionDraft(null, 'Blue', 'blue', '#0000ff'),
        optionDraft($black, 'Black', 'white', '#000000'),
    ], 'Color', OptionDisplay::Buttons));

    // Assert
    expect($colour->fresh()->name->get('en'))->toBe('Color')
        ->and($colour->fresh()->display)->toBe('buttons')
        ->and($colour->values()->get()->map(fn ($value) => [$value->id, $value->slug, $value->swatch])->all())->toBe([
            [$white->id, 'black', '#fafafa'],
            [$colour->values()->where('slug', 'blue')->value('id'), 'blue', '#0000ff'],
            [$black->id, 'white', '#000000'],
        ])
        // Red was only offered by the mug, without variants: the mug no longer offers it, nor the option.
        ->and($mug->options()->count())->toBe(0)
        ->and($green->fresh())->toBeNull()
        ->and($shirt->variants()->count())->toBe(2);
    Event::assertDispatchedTimes(OptionDefinitionValueDeleted::class, 2);
    Event::assertDispatchedTimes(OptionDefinitionValueCreated::class, 1);
    Event::assertDispatched(OptionDefinitionValueUpdated::class, fn ($event) => $event->valueId->value === $white->id && in_array('swatch', $event->changed, true));
});

it('refuses to delete a value variants use, saying how many on how many products', function () {
    // Arrange
    [$colour, $black, $white] = colours();
    $tee = Product::create(['name' => 'Tee', 'price' => Price::of(800)]);
    $tee->addOption($colour, [$black]);
    $tee->createCombinations([[$black]]);

    // Act
    try {
        app(SaveOptionDefinition::class)->handle(OptionDefinitionId::of($colour), optionPayload([optionDraft($white, 'White', 'white', '#ffffff')]));
        $thrown = null;
    } catch (OptionInUse $exception) {
        $thrown = $exception;
    }

    // Assert
    expect($thrown)->not->toBeNull()
        ->and($thrown->value)->toBe('black')
        ->and($thrown->variants)->toBe(2)
        ->and($thrown->products)->toBe(2)
        ->and($colour->values()->count())->toBe(3);
});

it('refuses another option\'s value id', function () {
    // Arrange
    [$colour] = colours();
    $size = OptionDefinition::create(['slug' => 'size', 'name' => ['en' => 'Size']]);
    $small = $size->values()->create(['slug' => 's', 'name' => ['en' => 'S']]);

    // Act & Assert
    expect(fn () => app(SaveOptionDefinition::class)->handle(OptionDefinitionId::of($colour), optionPayload([optionDraft($small, 'S', 's')])))
        ->toThrow(InvalidOptionValues::class);
    expect($small->fresh()->option_definition_id)->toBe($size->id);
});

it('deletes an option no variant uses, with what products offer of it', function () {
    // Arrange
    [, , , $red, , $mug] = colours();
    $size = OptionDefinition::create(['slug' => 'size', 'name' => ['en' => 'Size']]);
    $small = $size->values()->create(['slug' => 's', 'name' => ['en' => 'S']]);
    $mug->addOption($size, [$small]);
    Event::fake([OptionDefinitionDeleted::class]);

    // Act
    app(DeleteOptionDefinition::class)->handle(OptionDefinitionId::of($size));

    // Assert
    expect($size->fresh())->toBeNull()
        ->and($small->fresh())->toBeNull()
        ->and($mug->options()->with('definition')->get()->map(fn ($option) => $option->definition->slug)->all())->toBe(['colour']);
    Event::assertDispatched(OptionDefinitionDeleted::class, fn ($event) => $event->valueIds[0]->value === $small->id);
});

it('refuses to delete an option variants use, saying how many on how many products', function () {
    // Arrange
    [$colour] = colours();

    // Act & Assert
    expect(fn () => app(DeleteOptionDefinition::class)->handle(OptionDefinitionId::of($colour)))
        ->toThrow(fn (OptionInUse $exception) => expect([$exception->value, $exception->variants, $exception->products])->toBe([null, 2, 1]));
    expect($colour->fresh())->not->toBeNull();
});

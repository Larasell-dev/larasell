<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Facades\Audit;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Actions\AttributeValueDraft;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Actions\SaveProductAttribute;
use Larasell\Larasell\Catalog\Actions\SaveProductAttributePayload;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Catalog\AttributeCardinality;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Options\Actions\DeleteOptionDefinition;
use Larasell\Larasell\Catalog\Options\Actions\OptionValueDraft;
use Larasell\Larasell\Catalog\Options\Actions\SaveOptionDefinition;
use Larasell\Larasell\Catalog\Options\Actions\SaveOptionDefinitionPayload;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDisplay;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Customers\Actions\CreateCustomerGroup;
use Larasell\Larasell\Customers\Actions\CustomerGroupPayload;
use Larasell\Larasell\Customers\CustomerGroupCode;
use Larasell\Larasell\Customers\Models\CustomerGroup;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

/**
 * @return list<array{event: string, subject: ?string, properties: array<string, mixed>, origin: Origin}>
 */
function recordedRecords(): array
{
    $records = [];

    Audit::assertRecorded(AuditRecord::class, function (AuditRecord $record, Origin $origin) use (&$records) {
        $records[] = ['event' => $record->event(), 'subject' => $record->subject()?->type.':'.$record->subject()?->id, 'properties' => $record->properties(), 'origin' => $origin];

        return true;
    });

    return $records;
}

it('audits creating, updating and deleting a product with the origin the caller passed', function () {
    // Arrange
    Audit::fake();
    $origin = Origin::web(new SystemActor('back-office'));

    // Act
    $product = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]), $origin);
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['status' => 'hidden']), $origin);
    app(DeleteProduct::class)->handle(ProductId::of($product), $origin);

    // Assert
    $records = recordedRecords();

    expect(array_column($records, 'event'))->toBe(['product.created', 'product.updated', 'product.deleted'])
        ->and(array_unique(array_column($records, 'subject')))->toBe(["product:{$product->id}"])
        ->and($records[0]['properties'])->toBe(['name' => ['en' => 'Boots']])
        ->and($records[1]['properties']['changed'])->toContain('status')
        ->and(array_filter($records, fn (array $record) => $record['origin'] !== $origin))->toBe([]);
});

it('passes the origin on in the event, for the app\'s own listeners', function () {
    // Arrange
    Audit::fake();
    Event::fake([ProductUpdated::class]);
    $origin = Origin::api(new SystemActor('erp'));
    $product = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]), $origin);

    // Act
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['status' => 'hidden']), $origin);

    // Assert
    Event::assertDispatched(ProductUpdated::class, fn (ProductUpdated $event) => $event->origin === $origin);
});

it('rolls a change back when it can\'t be audited', function () {
    // Arrange
    Audit::fake()->failing();

    // Act & Assert
    expect(fn () => app(CreateCustomerGroup::class)->handle(new CustomerGroupPayload(code: CustomerGroupCode::of('wholesale'), name: new Translatable(['en' => 'Wholesale'])), Origin::cli(new SystemActor('import'))))
        ->toThrow(AuditFailed::class);

    expect(CustomerGroup::query()->count())->toBe(0);
});

it('audits saving and deleting options and attributes with the origin the caller passed', function () {
    // Arrange
    Audit::fake();
    $origin = Origin::web(new SystemActor('back-office'));
    $name = new Translatable(['en' => 'Colour']);

    // Act
    $colour = app(SaveOptionDefinition::class)->handle(null, new SaveOptionDefinitionPayload($name, 'colour', OptionDisplay::Swatch, [
        new OptionValueDraft(null, new Translatable(['en' => 'Red']), 'red', '#ff0000'),
    ]), $origin);
    app(DeleteOptionDefinition::class)->handle(OptionDefinitionId::of($colour), $origin);
    app(SaveProductAttribute::class)->handle(null, new SaveProductAttributePayload(new Translatable(['en' => 'Material']), 'material', ProductAttributeType::Text, AttributeScope::Inherited, AttributeCardinality::Single, [
        new AttributeValueDraft(null, new Translatable(['en' => 'Wool']), 'wool'),
    ]), $origin);

    // Assert
    $records = recordedRecords();

    expect(array_column($records, 'event'))->toBe(['option_definition.created', 'option_definition_value.created', 'option_definition.deleted', 'product_attribute.created', 'product_attribute_value.created'])
        ->and(array_filter($records, fn (array $record) => $record['origin'] !== $origin))->toBe([]);
});

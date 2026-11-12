<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Exceptions\InvalidAttributeValues;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Enums\ProductAttributeType;

/**
 * Creates an attribute, or updates one, and leaves it with exactly the given values: drafts
 * without an id are created, the others updated, and values left out deleted, which products
 * and variants lose. Positions follow the drafts' order. Each change goes through the
 * attribute's single-record actions, so it dispatches the same events, all in one transaction.
 */
final readonly class SaveProductAttribute
{
    private const string YES = '__boolean_true';

    private const string NO = '__boolean_false';

    public function __construct(
        private ConnectionInterface $db,
        private CreateProductAttribute $createAttribute,
        private UpdateProductAttribute $updateAttribute,
        private CreateProductAttributeValue $createValue,
        private UpdateProductAttributeValue $updateValue,
        private DeleteProductAttributeValue $deleteValue,
    ) {}

    /**
     * Without an attribute id, it creates one of the payload's type. A boolean attribute has
     * exactly its yes and no, by the slugs __boolean_true and __boolean_false; a number
     * attribute's values are the numbers their names spell.
     */
    public function handle(?AttributeId $attributeId, SaveProductAttributePayload $payload, Origin $origin): ProductAttribute
    {
        return $this->db->transaction(function () use ($origin, $attributeId, $payload): ProductAttribute {
            $fields = [
                'name' => $payload->name,
                'slug' => $payload->slug,
                'scope' => $payload->scope,
                'cardinality' => $payload->cardinality,
            ];
            if ($attributeId !== null && ProductAttribute::query()->findOrFail($attributeId->value)->type !== $payload->type) {
                throw InvalidAttributeValues::typeChanged($payload->slug);
            }

            $attribute = $attributeId === null
                ? $this->createAttribute->handle([...$fields, 'type' => $payload->type], $origin)
                : $this->updateAttribute->handle($attributeId, $fields, $origin);

            $existing = $attribute->values()->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $kept = [];
            $slugs = $attribute->values()->pluck('slug', 'id');

            if ($attribute->type === ProductAttributeType::Boolean) {
                $given = array_map(fn (AttributeValueDraft $draft): string => $draft->slug, $payload->values);
                sort($given);
                $renamesOnly = array_filter($payload->values, fn (AttributeValueDraft $draft): bool => $draft->id === null
                    ? $existing !== []
                    : ($slugs[$draft->id->value] ?? null) !== $draft->slug) === [];

                if ($given !== [self::NO, self::YES] || ! $renamesOnly) {
                    throw InvalidAttributeValues::notYesAndNo($attribute->slug);
                }
            }

            foreach ($payload->values as $draft) {
                if ($draft->id !== null && ! in_array($draft->id->value, $existing, true)) {
                    throw InvalidAttributeValues::ofAnotherAttribute($attribute->slug);
                }

                if ($draft->id !== null) {
                    $kept[] = $draft->id->value;
                }
            }

            // Deleted first, so a kept or new value can take a deleted one's slug.
            foreach (array_diff($existing, $kept) as $id) {
                $this->deleteValue->handle(new AttributeValueId($id), $origin);
            }

            // Slugs are unique per attribute and checked on each write, so values that change theirs
            // first give them up: swapping two slugs would otherwise collide halfway. Without the
            // action, so the change and its event come once, with the final slug.
            foreach ($payload->values as $draft) {
                if ($draft->id !== null && $slugs[$draft->id->value] !== $draft->slug) {
                    ProductAttributeValue::query()->whereKey($draft->id->value)->update(['slug' => "__saving-{$draft->id->value}"]);
                }
            }

            foreach ($payload->values as $index => $draft) {
                $fields = ['name' => $draft->name, 'slug' => $draft->slug, 'position' => $index + 1];

                // Numbers follow their names; text keeps the value it was created with.
                if ($draft->id === null || $attribute->type === ProductAttributeType::Number) {
                    $fields['value'] = self::typed($attribute, $draft);
                }

                $draft->id === null
                    ? $this->createValue->handle(AttributeId::of($attribute), $fields, $origin)
                    : $this->updateValue->handle($draft->id, $fields, $origin);
            }

            return $attribute->refresh();
        });
    }

    /** The value a draft stands for: its default name, as a number or a boolean by the attribute's type. */
    private static function typed(ProductAttribute $attribute, AttributeValueDraft $draft): string|int|float|bool
    {
        $name = $draft->name->get();

        return match ($attribute->type) {
            ProductAttributeType::Boolean => $draft->slug === self::YES,
            ProductAttributeType::Number => is_numeric($name) ? $name + 0 : throw InvalidAttributeValues::notANumber($attribute->slug, $name),
            ProductAttributeType::Text => $name,
        };
    }
}

<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueCreated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class CreateProductAttributeValue
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  e.g. slug, name, value (checked against the attribute's type), position */
    public function handle(AttributeId $attributeId, array $attributes, Origin $origin): ProductAttributeValue
    {
        return $this->db->transaction(function () use ($origin, $attributeId, $attributes): ProductAttributeValue {
            $attribute = ProductAttribute::query()->lockForUpdate()->findOrFail($attributeId->value);
            $value = $attribute->values()->create($attributes);

            ProductAttributeValueCreated::dispatch(AttributeValueId::of($value), AttributeId::of($attribute), origin: $origin);

            return $value;
        });
    }
}

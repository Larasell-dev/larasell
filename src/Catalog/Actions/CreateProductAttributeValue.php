<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueCreated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class CreateProductAttributeValue
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  e.g. slug, name, value (checked against the attribute's type), position */
    public function handle(int $attributeId, array $attributes): ProductAttributeValue
    {
        return $this->db->transaction(function () use ($attributeId, $attributes): ProductAttributeValue {
            $attribute = ProductAttribute::query()->lockForUpdate()->findOrFail($attributeId);
            $value = $attribute->values()->create($attributes);

            ProductAttributeValueCreated::dispatch($value->id, $attribute->id);

            return $value;
        });
    }
}

<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\Events\ProductAttributeUpdated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;

final readonly class UpdateProductAttribute
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(AttributeId $attributeId, array $attributes): ProductAttribute
    {
        return $this->db->transaction(function () use ($attributeId, $attributes): ProductAttribute {
            $attribute = ProductAttribute::query()->lockForUpdate()->findOrFail($attributeId->value);
            $changed = array_keys($attribute->fill($attributes)->getDirty());

            if ($changed !== []) {
                $attribute->save();
                ProductAttributeUpdated::dispatch($attributeId, $changed);
            }

            return $attribute;
        });
    }
}

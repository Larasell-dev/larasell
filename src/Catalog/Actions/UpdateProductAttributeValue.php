<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueUpdated;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class UpdateProductAttributeValue
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(AttributeValueId $valueId, array $attributes, Origin $origin): ProductAttributeValue
    {
        return $this->db->transaction(function () use ($origin, $valueId, $attributes): ProductAttributeValue {
            $value = ProductAttributeValue::query()->lockForUpdate()->findOrFail($valueId->value);
            $changed = array_keys($value->fill($attributes)->getDirty());

            if ($changed !== []) {
                $value->save();
                ProductAttributeValueUpdated::dispatch($valueId, new AttributeId($value->product_attribute_id), $changed, origin: $origin);
            }

            return $value;
        });
    }
}

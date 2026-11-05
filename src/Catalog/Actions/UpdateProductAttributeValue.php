<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueUpdated;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class UpdateProductAttributeValue
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(int $valueId, array $attributes): ProductAttributeValue
    {
        return $this->db->transaction(function () use ($valueId, $attributes): ProductAttributeValue {
            $value = ProductAttributeValue::query()->lockForUpdate()->findOrFail($valueId);
            $changed = array_keys($value->fill($attributes)->getDirty());

            if ($changed !== []) {
                $value->save();
                ProductAttributeValueUpdated::dispatch($value->id, $value->product_attribute_id, $changed);
            }

            return $value;
        });
    }
}

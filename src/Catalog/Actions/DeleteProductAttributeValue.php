<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueDeleted;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class DeleteProductAttributeValue
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(AttributeValueId $valueId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $valueId): void {
            $value = ProductAttributeValue::query()->lockForUpdate()->findOrFail($valueId->value);

            $value->delete();

            ProductAttributeValueDeleted::dispatch($valueId, new AttributeId($value->product_attribute_id), origin: $origin);
        });
    }
}

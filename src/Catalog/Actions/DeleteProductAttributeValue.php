<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueDeleted;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class DeleteProductAttributeValue
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(int $valueId): void
    {
        $this->db->transaction(function () use ($valueId): void {
            $value = ProductAttributeValue::query()->lockForUpdate()->findOrFail($valueId);

            $value->delete();

            ProductAttributeValueDeleted::dispatch($value->id, $value->product_attribute_id);
        });
    }
}

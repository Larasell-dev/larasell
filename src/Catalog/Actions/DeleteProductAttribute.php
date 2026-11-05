<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeDeleted;
use Larasell\Larasell\Catalog\Models\ProductAttribute;

/** Deletes an attribute with its values; products and variants lose them. */
final readonly class DeleteProductAttribute
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(AttributeId $attributeId): void
    {
        $this->db->transaction(function () use ($attributeId): void {
            $attribute = ProductAttribute::query()->lockForUpdate()->findOrFail($attributeId->value);
            $valueIds = AttributeValueId::many($attribute->values()->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id));

            $attribute->delete();

            ProductAttributeDeleted::dispatch($attributeId, $valueIds);
        });
    }
}

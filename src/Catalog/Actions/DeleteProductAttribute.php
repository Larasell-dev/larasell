<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductAttributeDeleted;
use Larasell\Larasell\Catalog\Models\ProductAttribute;

/** Deletes an attribute with its values; products and variants lose them. */
final readonly class DeleteProductAttribute
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(int $attributeId): void
    {
        $this->db->transaction(function () use ($attributeId): void {
            $attribute = ProductAttribute::query()->lockForUpdate()->findOrFail($attributeId);
            $valueIds = array_values($attribute->values()->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

            $attribute->delete();

            ProductAttributeDeleted::dispatch($attribute->id, $valueIds);
        });
    }
}

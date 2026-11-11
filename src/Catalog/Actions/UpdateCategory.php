<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Models\Category;

final readonly class UpdateCategory
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CategoryId $categoryId, UpdateCategoryPayload $payload, Origin $origin): Category
    {
        return $this->db->transaction(function () use ($origin, $categoryId, $payload): Category {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId->value);
            $changed = array_keys($category->fill(array_filter(
                ['name' => $payload->name, 'status' => $payload->status],
                fn (mixed $value): bool => $value !== null,
            ))->getDirty());

            if ($changed !== []) {
                $category->save();
            }

            // Attachments are what the app saves with the category, so their listeners run either way.
            if ($changed !== [] || ! $payload->attachments->isEmpty()) {
                CategoryUpdated::dispatch($categoryId, $changed, origin: $origin, attachments: $payload->attachments);
            }

            return $category;
        });
    }
}

<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Models\Category;

final readonly class CreateCategory
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CreateCategoryPayload $payload): Category
    {
        return $this->db->transaction(function () use ($payload): Category {
            if ($payload->parentId !== null) {
                // Holds the parent until the new child is in, so a concurrent delete or move waits.
                Category::query()->lockForUpdate()->findOrFail($payload->parentId->value);
            }

            $category = Category::query()->create([
                'name' => $payload->name,
                'status' => $payload->status,
                'parent_id' => $payload->parentId?->value,
            ]);

            CategoryCreated::dispatch(CategoryId::of($category), $payload->parentId, $payload->extensions);

            return $category;
        });
    }
}

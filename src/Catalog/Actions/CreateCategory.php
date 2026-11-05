<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Models\Category;

final readonly class CreateCategory
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  Category attributes, e.g. name, parent_id, status, position */
    public function handle(array $attributes): Category
    {
        return $this->db->transaction(function () use ($attributes): Category {
            if (isset($attributes['parent_id'])) {
                // Holds the parent until the new child is in, so a concurrent delete or move waits.
                Category::query()->lockForUpdate()->findOrFail($attributes['parent_id']);
            }

            $category = Category::query()->create($attributes);

            CategoryCreated::dispatch(CategoryId::of($category), $category->parent_id === null ? null : new CategoryId($category->parent_id));

            return $category;
        });
    }
}

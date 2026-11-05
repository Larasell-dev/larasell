<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Models\Category;

/** Deletes a category. Its children become top-level categories; its products stay. */
final readonly class DeleteCategory
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(int $categoryId): void
    {
        $this->db->transaction(function () use ($categoryId): void {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId);
            $childIds = array_values(Category::query()->where('parent_id', $category->id)->orderBy('id')->lockForUpdate()
                ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

            $category->delete();

            CategoryDeleted::dispatch($category->id, $category->parent_id, $category->name, $childIds);
        });
    }
}

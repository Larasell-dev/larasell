<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Models\Category;

/** Deletes a category. Its children become top-level categories; its products stay. */
final readonly class DeleteCategory
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CategoryId $categoryId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $categoryId): void {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId->value);
            $childIds = CategoryId::many(Category::query()->where('parent_id', $category->id)->orderBy('id')->lockForUpdate()
                ->pluck('id')->map(fn (mixed $id): int => (int) $id));

            $category->delete();

            CategoryDeleted::dispatch($categoryId, $category->parent_id === null ? null : new CategoryId($category->parent_id), $category->name, $childIds, $origin);
        });
    }
}

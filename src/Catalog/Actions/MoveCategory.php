<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Exceptions\CategoryCycle;
use Larasell\Larasell\Catalog\Models\Category;
use LogicException;

/** Gives a category another parent (null for the top level) or position. Its descendants move with it. */
final readonly class MoveCategory
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  int|null  $position  null puts it after its new siblings */
    public function handle(int $categoryId, ?int $parentId, ?int $position = null): Category
    {
        return $this->db->transaction(function () use ($categoryId, $parentId, $position): Category {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId);
            $this->assertNoCycle($category, $parentId);

            $oldParentId = $category->parent_id;
            $category->parent_id = $parentId;
            $category->position = $position ?? ($oldParentId === $parentId ? $category->position : $this->nextPosition($parentId));

            if (! $category->isDirty()) {
                return $category;
            }

            $category->save();
            CategoryMoved::dispatch($category->id, $oldParentId, $parentId);

            return $category;
        });
    }

    /**
     * Walks up from the new parent, locking each ancestor: a concurrent move of one of them waits,
     * and its result is read before deciding, so two moves cannot close a cycle together.
     */
    private function assertNoCycle(Category $category, ?int $parentId): void
    {
        $seen = [];

        for ($id = $parentId; $id !== null; $id = Category::query()->lockForUpdate()->findOrFail($id)->parent_id) {
            if ($id === $category->id) {
                throw new CategoryCycle($category->id, (int) $parentId);
            }

            // The tree was acyclic before this move, so walking up must end; a repeat means it was not.
            if (isset($seen[$id])) {
                throw new LogicException("The category tree already has a cycle through category [{$id}].");
            }

            $seen[$id] = true;
        }
    }

    private function nextPosition(?int $parentId): int
    {
        $last = Category::query()->where('parent_id', $parentId)->max('position');

        return $last === null ? 0 : (int) $last + 1;
    }
}

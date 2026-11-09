<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Exceptions\CategoryCycle;
use Larasell\Larasell\Catalog\Models\Category;
use LogicException;

/** Gives a category another parent (null for the top level) or position. Its descendants move with it. */
final readonly class MoveCategory
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * Numbers its new siblings again from 0, with it at the payload's position.
     *
     * @throws CategoryCycle when the new parent is the category or one below it
     */
    public function handle(CategoryId $categoryId, MoveCategoryPayload $payload): Category
    {
        $parentId = $payload->parentId;

        // Retried on deadlock: two opposite moves (A below B, B below A) lock the same categories in
        // reverse order, and the retry sees the other's move and refuses the cycle.
        return $this->db->transaction(function () use ($categoryId, $parentId, $payload): Category {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId->value);
            if ($parentId !== null) {
                $this->assertNoCycle($category, $parentId);
            }

            $oldParentId = $category->parent_id;
            $siblings = Category::query()
                ->where('parent_id', $parentId?->value)
                ->whereKeyNot($category->id)
                ->orderBy('position')->orderBy('id')
                ->lockForUpdate()
                ->get();
            $siblings->splice(min($payload->position ?? $siblings->count(), $siblings->count()), 0, [$category]);

            $category->parent_id = $parentId?->value;
            $moved = $category->isDirty('parent_id');

            // Saving only writes the categories whose parent or position changed.
            foreach ($siblings->values() as $position => $sibling) {
                $sibling->position = $position;
                $sibling->save();
            }

            if ($moved || $category->wasChanged('position')) {
                CategoryMoved::dispatch($categoryId, $oldParentId === null ? null : new CategoryId($oldParentId), $parentId);
            }

            return $category;
        }, attempts: 3);
    }

    /**
     * Walks up from the new parent, locking each ancestor: a concurrent move of one of them waits,
     * and its result is read before deciding, so two moves cannot close a cycle together.
     */
    private function assertNoCycle(Category $category, CategoryId $parentId): void
    {
        $seen = [];

        for ($id = $parentId->value; $id !== null; $id = Category::query()->lockForUpdate()->findOrFail($id)->parent_id) {
            if ($id === $category->id) {
                throw new CategoryCycle(CategoryId::of($category), $parentId);
            }

            // The tree was acyclic before this move, so walking up must end; a repeat means it was not.
            if (isset($seen[$id])) {
                throw new LogicException("The category tree already has a cycle through category [{$id}].");
            }

            $seen[$id] = true;
        }
    }
}

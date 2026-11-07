<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Models\Category;

/**
 * Deletes a category with every category below it. Each goes through DeleteCategory, deepest
 * first, so each is announced and none is left without its parent. Their products stay.
 */
final readonly class DeleteCategoryTree
{
    public function __construct(private ConnectionInterface $db, private DeleteCategory $delete) {}

    public function handle(CategoryId $categoryId): void
    {
        $this->db->transaction(function () use ($categoryId): void {
            $tree = $this->db->table('larasell_categories')->newQuery()->fromRaw(
                '(with recursive tree (id, depth) as (
                    select id, 0 from larasell_categories where id = ?
                    union all
                    select child.id, tree.depth + 1 from larasell_categories as child join tree on child.parent_id = tree.id
                ) select id, depth from tree) as tree',
                [$categoryId->value],
            );

            Category::query()
                ->joinSub($tree, 'tree', 'tree.id', '=', 'larasell_categories.id')
                ->orderByDesc('tree.depth')->orderByDesc('larasell_categories.id')
                ->pluck('larasell_categories.id')
                ->each(fn (mixed $id) => $this->delete->handle(new CategoryId((int) $id)));
        });
    }
}

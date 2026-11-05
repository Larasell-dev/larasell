<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Translatable;

/**
 * A category was deleted. Its children are top-level categories now.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CategoryDeleted
{
    use Dispatchable;

    /**
     * @param  list<CategoryId>  $childIds
     */
    public function __construct(
        public CategoryId $categoryId,
        public ?CategoryId $parentId,
        public Translatable $name,
        public array $childIds,
    ) {}
}

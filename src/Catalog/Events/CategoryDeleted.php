<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
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
     * @param  list<int>  $childIds
     */
    public function __construct(
        public int $categoryId,
        public ?int $parentId,
        public Translatable $name,
        public array $childIds,
    ) {}
}

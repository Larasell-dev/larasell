<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\CategoryId;

/**
 * A category was created.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CategoryCreated
{
    use Dispatchable;

    public function __construct(
        public CategoryId $categoryId,
        public ?CategoryId $parentId,
    ) {}
}

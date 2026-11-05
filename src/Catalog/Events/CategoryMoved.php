<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A category got another parent or position. Its descendants moved with it.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CategoryMoved
{
    use Dispatchable;

    public function __construct(
        public int $categoryId,
        public ?int $oldParentId,
        public ?int $newParentId,
    ) {}
}

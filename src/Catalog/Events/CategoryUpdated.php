<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Extensions;

/**
 * A category changed, other than its place in the tree; $changed names its changed attributes,
 * empty when only extensions were passed along.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CategoryUpdated
{
    use Dispatchable;

    /**
     * @param  list<string>  $changed
     */
    public function __construct(
        public CategoryId $categoryId,
        public array $changed,
        /** What the app passed along with the action for its own listeners. */
        public Extensions $extensions = new Extensions,
    ) {}
}

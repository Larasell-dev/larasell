<?php

namespace Larasell\Larasell\Catalog\Options\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;

/**
 * An option definition's value was deleted; products that offered it without variants using it no longer do.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class OptionDefinitionValueDeleted
{
    use Dispatchable;

    public function __construct(
        public OptionDefinitionValueId $valueId,
        public OptionDefinitionId $definitionId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}

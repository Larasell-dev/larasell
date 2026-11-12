<?php

namespace Larasell\Larasell\Catalog\Options\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;

/**
 * A value was added to an option definition.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class OptionDefinitionValueCreated
{
    use Dispatchable;

    public function __construct(
        public OptionDefinitionValueId $valueId,
        public OptionDefinitionId $definitionId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}

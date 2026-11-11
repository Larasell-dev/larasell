<?php

namespace Larasell\Larasell\Catalog\Options\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;

/**
 * An option definition's value changed: which of its fields.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class OptionDefinitionValueUpdated
{
    use Dispatchable;

    /** @param  list<string>  $changed */
    public function __construct(
        public OptionDefinitionValueId $valueId,
        public OptionDefinitionId $definitionId,
        public array $changed,
    ) {}
}

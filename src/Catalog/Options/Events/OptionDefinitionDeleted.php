<?php

namespace Larasell\Larasell\Catalog\Options\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;

/**
 * An option definition was deleted with its values, which products offered without variants using them.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class OptionDefinitionDeleted
{
    use Dispatchable;

    /** @param  list<OptionDefinitionValueId>  $valueIds  The values deleted with it. */
    public function __construct(
        public OptionDefinitionId $definitionId,
        public array $valueIds,
    ) {}
}

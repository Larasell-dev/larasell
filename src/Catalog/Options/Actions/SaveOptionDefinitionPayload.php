<?php

namespace Larasell\Larasell\Catalog\Options\Actions;

use Larasell\Larasell\Catalog\Options\OptionDisplay;
use Larasell\Larasell\Translatable;

/** An option as SaveOptionDefinition leaves it, values included. */
final readonly class SaveOptionDefinitionPayload
{
    /**
     * @param  list<OptionValueDraft>  $values  All of its values, in the order the store shows them.
     */
    public function __construct(
        /** All of its translations, e.g. `$definition->name->with('de', 'Farbe')`. */
        public Translatable $name,
        public string $slug,
        public OptionDisplay $display,
        public array $values,
    ) {}
}

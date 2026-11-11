<?php

namespace Larasell\Larasell\Catalog\Options\Actions;

use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;
use Larasell\Larasell\Translatable;

/** One of the values SaveOptionDefinition leaves an option with. */
final readonly class OptionValueDraft
{
    public function __construct(
        /** The value it changes, or null for a new one. */
        public ?OptionDefinitionValueId $id,
        /** All of its translations, e.g. `$value->name->with('de', 'Schwarz')`. */
        public Translatable $name,
        public string $slug,
        /** A colour such as #000000, shown when the option displays as swatches. */
        public ?string $swatch = null,
    ) {}
}

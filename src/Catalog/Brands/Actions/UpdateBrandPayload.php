<?php

namespace Larasell\Larasell\Catalog\Brands\Actions;

use Larasell\Larasell\Catalog\Keep;
use Larasell\Larasell\Translatable;

/** What changes about a brand; null keeps the name and slug, Keep::Value the description, which null clears. */
final readonly class UpdateBrandPayload
{
    public function __construct(
        /** All of its translations. */
        public ?Translatable $name = null,
        public ?string $slug = null,
        public Translatable|Keep|null $description = Keep::Value,
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return array_filter(
            ['name' => $this->name, 'slug' => $this->slug],
            fn (mixed $value): bool => $value !== null,
        ) + ($this->description === Keep::Value ? [] : ['description' => $this->description]);
    }
}
